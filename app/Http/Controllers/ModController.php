<?php namespace App\Http\Controllers;

use App\Libraries\UrlUtils;
use App\Mod;
use App\Modversion;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use ZipArchive;

class ModController extends Controller
{

    public function __construct()
    {
        $this->middleware('solder_mods');
    }

    public function getIndex()
    {
        return redirect('mod/list');
    }

    public function getList()
    {
        $mods = Mod::with(
            [
                'versions' => function ($query) {
                    $query->orderBy('modversions.updated_at', 'desc');
                }
            ]
        )
            ->get();
        return view('mod.list')->with(['mods' => $mods]);
    }

    public function getView($mod_id = null)
    {
        $mod = Mod::with('versions')
            ->with('versions.builds')
            ->with('versions.builds.modpack')
            ->find($mod_id);

        if (empty($mod)) {
            return redirect('mod/list')->withErrors(new MessageBag(['Mod not found']));
        }

        return view('mod.view')->with(['mod' => $mod]);
    }

    public function getCreate()
    {
        return view('mod.create');
    }

    public function postCreate()
    {
        $rules = [
            'name' => 'required|unique:mods',
            'pretty_name' => 'required',
            'link' => 'nullable|url',
        ];
        $messages = [
            'name.required' => 'You must fill in a mod slug name.',
            'name.unique' => 'The slug you entered is already taken',
            'pretty_name.required' => 'You must enter in a mod name',
            'link.url' => 'You must enter a properly formatted Website',
        ];

        $validation = Validator::make(Request::all(), $rules, $messages);
        if ($validation->fails()) {
            return redirect('mod/create')->withErrors($validation->messages());
        }

        $mod = new Mod();
        $mod->name = Str::slug(Request::input('name'));
        $mod->pretty_name = Request::input('pretty_name');
        $mod->author = Request::input('author');
        $mod->description = Request::input('description');
        $mod->link = Request::input('link');
        $mod->save();
        return redirect('mod/view/' . $mod->id);
    }

    public function getDelete($mod_id = null)
    {
        $mod = Mod::find($mod_id);
        if (empty($mod)) {
            return redirect('mod/list')->withErrors(new MessageBag(['Mod not found']));
        }

        return view('mod.delete')->with(['mod' => $mod]);
    }

    public function postModify($mod_id = null)
    {
        $mod = Mod::find($mod_id);
        if (empty($mod)) {
            return redirect('mod/list')->withErrors(new MessageBag(['Error modifying mod - Mod not found']));
        }

        $rules = [
            'pretty_name' => 'required',
            'name' => 'required|unique:mods,name,' . $mod->id,
            'link' => 'nullable|url',
        ];

        $messages = [
            'name.required' => 'You must fill in a mod slug name.',
            'name.unique' => 'The slug you entered is already in use by another mod',
            'pretty_name.required' => 'You must enter in a mod name',
            'link.url' => 'You must enter a properly formatted Website',
        ];

        $validation = Validator::make(Request::all(), $rules, $messages);
        if ($validation->fails()) {
            return redirect('mod/view/' . $mod->id)->withErrors($validation->messages());
        }

        $mod->pretty_name = Request::input('pretty_name');
        $mod->name = Request::input('name');
        $mod->author = Request::input('author');
        $mod->description = Request::input('description');
        $mod->link = Request::input('link');
        $mod->save();
        Cache::forget('mod:' . $mod->name);

        return redirect('mod/view/' . $mod->id)->with('success', 'Mod successfully edited.');
    }

    public function postDelete($mod_id = null)
    {
        $mod = Mod::find($mod_id);
        if (empty($mod)) {
            return redirect('mod/list')->withErrors(new MessageBag(['Error deleting mod - Mod not found']));
        }

        foreach ($mod->versions as $ver) {
            $ver->builds()->sync([]);
            $ver->delete();
        }
        $mod->delete();
        Cache::forget('mod:' . $mod->name);

        return redirect('mod/list')->with('success', 'Mod deleted!');
    }

    public function postUpload()
    {
        Log::debug('Client is uploading a file.');
        $file = Request::file('file');
        $clientFilename = $file->getClientOriginalName();
        Log::debug('Client uploaded file ' . $clientFilename);
        Storage::disk('local')->putFileAs('modstmp/', $file, $clientFilename);
        Log::debug('Saved file as modstmp/'.$clientFilename);
        if(!$modInfo = $this->read_mod_info($clientFilename)[0]) {
            Log::error('Could not read mod-info from ' . $clientFilename . '.');
            return response()->json([
                'success' => true,
                'modInfo' => false,
                'error' => [
                    'message' => 'Could not load mod info.'
                ],
                'format' => 'info-error'
            ]);
        }
        //validate info:
        if(!$modInfo = $this->validate_mod_info($modInfo)) {
            Log::warning($clientFilename . ' did not contain a mcmod.info file to read details out of.');
            return response()->json([
                'success' => true,
                'modInfo' => false,
                'error' => [
                    'message' => 'Could not load mod info.'
                ],
                'format' => 'info-error'
            ]); 
        }
        //create mod zip file! move to app/public/mods/modslug/modslug-version.zip
        $newFileName = $modInfo->modid.'-'.$modInfo->version.'.zip';
        Log::debug('Creating new zip file as modstmp/' . $newFileName);
        $newZipFile = new ZipArchive;
        $res = $newZipFile->open('/var/www/storage/app/modstmp/'.$newFileName, ZipArchive::OVERWRITE);
        if($res !== TRUE) {
            //Could not create zipfile:
            Log::error('Could not create zipfile modstmp/' . $newFileName);
            return response()->json([
                'success' => false,
                'modInfo' => false,
                'error' => [
                    'message' => 'Could not create zip file.'
                ],
                'format' => 'zip-create-error'
            ]);
            //Add mods/ folder:
        }
        if($newZipFile->addEmptyDir('mods')) {
            //add the file now.
            if($newZipFile->addFile('/var/www/storage/app/modstmp/'.$clientFilename, 'mods/'.$clientFilename)) {
                //Add successfull, close archive.
                $newZipFile->close();
                //now move new file.
                Storage::move('modstmp/'.$newFileName, 'public/mods/'.$modInfo->modid.'/'.$newFileName);

                //return proposed data for new mod, or add version?
                return response()->json([
                    'success' => true,
                    'modInfo' => $modInfo,
                    'error' => [
                        'message' => 'No error.'
                    ],
                    'format' => 'jar',
                ]);
            }
        } else {
            Log::error('Could not create folder inside zip file: modstmp/'. $newFileName);
            //could not create folder. what do?
            return response()->json([
                'success' => false,
                'modInfo' => false,
                'error' => [
                    'message' => 'Could not create mod folder.'
                ],
                'format' => 'zip-create-error'
            ]); 
        }
    }

    public function anyRehash()
    {
        if (!Request::ajax()) {
            abort(404);
        }

        $md5 = Request::input('md5');
        $ver_id = Request::input('version-id');
        if (empty($ver_id)) {
            return response()->json([
                'status' => 'error',
                'reason' => 'Missing Post Data',
            ]);
        }

        $ver = Modversion::find($ver_id);
        if (empty($ver)) {
            return response()->json([
                'status' => 'error',
                'reason' => 'Could not pull mod version from database',
            ]);
        }

        if (empty($md5)) {
            $md5Request = $this->mod_md5($ver->mod, $ver->version);
            if ($md5Request['success']) {
                $md5 = $md5Request['md5'];
            }
        } else {
            $md5Request = $this->mod_md5($ver->mod, $ver->version);
            $providedfile_md5 = !$md5Request['success'] ? "Null" : $md5Request['md5'];
        }

        if ($md5Request['success'] && !empty($md5)) {
            if ($md5 == $md5Request['md5']) {
                $ver->filesize = $md5Request['filesize'];
                $ver->md5 = $md5;
                $ver->save();
                return response()->json([
                    'status' => 'success',
                    'version_id' => $ver->id,
                    'md5' => $ver->md5,
                    'filesize' => $ver->humanFilesize(),
                ]);
            } else {
                $ver->filesize = $md5Request['filesize'];
                $ver->md5 = $md5;
                $ver->save();
                return response()->json([
                    'status' => 'warning',
                    'version_id' => $ver->id,
                    'md5' => $ver->md5,
                    'filesize' => $ver->humanFilesize(),
                    'reason' => 'MD5 provided does not match file MD5: ' . $providedfile_md5,
                ]);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'reason' => 'Remote MD5 failed. ' . $md5Request['message'],
            ]);
        }
    }

    public function anyAddVersion()
    {
        if (!Request::ajax()) {
            abort(404);
        }

        $mod_id = Request::input('mod-id');
        $md5 = Request::input('add-md5');
        $version = Request::input('add-version');
        if (empty($mod_id) || empty($version)) {
            return response()->json([
                'status' => 'error',
                'reason' => 'Missing Post Data'
            ]);
        }

        $mod = Mod::find($mod_id);
        if (empty($mod)) {
            return response()->json([
                'status' => 'error',
                'reason' => 'Could not pull mod from database'
            ]);
        }

        if (Modversion::where([
                'mod_id' => $mod_id,
                'version' => $version,
            ])->count() > 0) {
            return response()->json([
                'status' => 'error',
                'reason' => 'That mod version already exists',
            ]);
        }

        if (empty($md5)) {
            $file_md5 = $this->mod_md5($mod, $version);
            if ($file_md5['success']) {
                $md5 = $file_md5['md5'];
            }
        } else {
            $file_md5 = $this->mod_md5($mod, $version);
            $pfile_md5 = !$file_md5['success'] ? "Null" : $file_md5['md5'];
        }

        $ver = new Modversion();
        $ver->mod_id = $mod->id;
        $ver->version = $version;

        if ($file_md5['success'] && !empty($md5)) {
            if ($md5 === $file_md5['md5']) {
                $ver->filesize = $file_md5['filesize'];
                $ver->md5 = $md5;
                $ver->save();
                return response()->json([
                    'status' => 'success',
                    'version' => $ver->version,
                    'md5' => $ver->md5,
                    'filesize' => $ver->humanFilesize(),
                ]);
            } else {
                $ver->filesize = $file_md5['filesize'];
                $ver->md5 = $md5;
                $ver->save();
                return response()->json([
                    'status' => 'warning',
                    'version' => $ver->version,
                    'md5' => $ver->md5,
                    'filesize' => $ver->humanFilesize(),
                    'reason' => 'MD5 provided does not match file MD5: ' . $pfile_md5,
                ]);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'reason' => 'Remote MD5 failed. ' . $file_md5['message'],
            ]);
        }
    }

    public function anyDeleteVersion($ver_id = null)
    {
        if (!Request::ajax()) {
            abort(404);
        }

        if (empty($ver_id)) {
            return response()->json([
                'status' => 'error',
                'reason' => 'Missing Post Data'
            ]);
        }

        $ver = Modversion::find($ver_id);
        if (empty($ver)) {
            return response()->json([
                'status' => 'error',
                'reason' => 'Could not pull mod version from database'
            ]);
        }

        $old_id = $ver->id;
        $old_version = $ver->version;
        $ver->delete();
        return response()->json([
            'status' => 'success',
            'version' => $old_version,
            'version_id' => $old_id
        ]);
    }

    private function mod_md5($mod, $version)
    {
        $location = config('solder.repo_location');
        $URI = $location . 'mods/' . $mod->name . '/' . $mod->name . '-' . $version . '.zip';

        if (file_exists($URI)) {
            Log::info('Found \'' . $URI . '\'');
            try {
                $filesize = filesize($URI);
                $md5 = md5_file($URI);
                return ['success' => true, 'md5' => $md5, 'filesize' => $filesize];
            } catch (Exception $e) {
                Log::error("Error attempting to md5 the file: " . $URI);
                return ['success' => false, 'message' => $e->getMessage()];
            }
        } else {
            if (filter_var($URI, FILTER_VALIDATE_URL)) {
                Log::warning('File \'' . $URI . '\' was not found.');
                return $this->remote_mod_md5($mod, $version, $location);
            } else {
                $error = $URI . ' is not a valid URI';
                Log::error($error);
                return ['success' => false, 'message' => $error];
            }
        }
    }

    private function remote_mod_md5($mod, $version, $location, $attempts = 0)
    {
        $URL = $location . 'mods/' . $mod->name . '/' . $mod->name . '-' . $version . '.zip';

        $hash = UrlUtils::get_remote_md5($URL);

        if (!($hash['success']) && $attempts <= 3) {
            Log::warning("Error attempting to remote MD5 file " . $mod->name . " version " . $version . " located at " . $URL . ".");
            return $this->remote_mod_md5($mod, $version, $location, $attempts + 1);
        }

        return $hash;
    }

    private function read_mod_info($filename)
    {
        //Open as Zip file.
        $zip = new ZipArchive;
        $mcmodInfo = false;
        $res = $zip->open('/var/www/storage/app/modstmp/'.$filename, ZipArchive::RDONLY);
        if($res === TRUE) {
            $manifestIndex = $zip->locateName('mcmod.info', ZipArchive::FL_NOCASE);
            //get content (Will be in json):
            $mcmodInfoContent = $zip->getFromIndex($manifestIndex);
            $mcmodInfo = json_decode($mcmodInfoContent);
        }
        $zip->close();
        return $mcmodInfo;
    }

    /**
     * Validates and fills missing info according to: https://mcforge.readthedocs.io/en/1.13.x/gettingstarted/structuring/#keeping-your-code-clean-using-sub-packages
     *
     * @param [type] $modInfo
     * @return object|boolean
     */
    private function validate_mod_info($modInfo)
    {
        $info = array();
        //check if important infos are set and if not, set default value:
        if(!isset($modInfo->modid)) {
            return false; //without modid, the info is not really usable.
        } else {
            //generate mod slug:
            $info['modid'] = Str::slug($modInfo->modid);
        }
        $info['name'] = $modInfo->name ?? 'no pretty name given.'; //pretty name. required
        $info['description'] = $modInfo->description ?? 'no description given'; //description of the mod.
        $info['version'] = $modInfo->version ?? 'no mod version given.'; //version of the mod
        $info['mcversion'] = $modInfo->mcversion ?? 'no minecraft version given.'; //version of mc this version of the mod works on. ~ can be 1.12.x || 1.12.2  || 1.12.1-1.13.1 or any variation
        $info['url'] = $modInfo->url ?? 'no url given.'; //shows url of author? OPTIONAL!
        $info['updateUrl'] = $modInfo->updateUrl ?? 'no updateurl given.'; //link to a url with versions listed.
        $info['updateJson'] = $modInfo->updateJson ?? 'no updatejson url given.'; //link to a json "file" with versions listed.
        $info['authorList'] = $modInfo->authorList ?? ['no author list provided.']; //Array of persons that authored this mod.
        $info['credits'] = $modInfo->credits ?? 'no credits given.'; //credits? idk OPTIONAL!
        $info['logoFile'] = $modInfo->logoFile ?? 'no logo file path provided.'; //If the author included an logo, it will be referenced here.
        $info['screenshots'] = $modInfo->screenshots ?? ['no screenshot urls provided']; //Screenshots of the mod. OPTIONAL!
        $info['parent'] = $modInfo->parent ?? 'no parent id provided'; //id of the parent mod. for example used in modular mods as Buildcraft or mekanism.
        $info['useDependencyInformation'] = $modInfo->useDependencyInformation ?? false; //if true, the next three dependency args should be used.
        $info['requiredMods'] = $modInfo->requiredMods ?? ['no requirements provided.']; //A list of modids. If one is missing, the game will crash. 
        $info['dependencies'] = $modInfo->dependencies ?? ['no dependencies provided']; //A list of modids. All of the listed mods will load before this one. If one is not present, nothing happens.
        $info['dpendants'] = $modInfo->dpendants ?? ['no dependants provided.']; //A list of modids. All of the listed mods will load after this one. If one is not present, nothing happens.
        //send filled info back
        return (object)$info;
    }
}
