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
use Yosymfony\Toml\Toml;

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
        //gather all known mod slugs, to display select box:
        $mods = Mod::with(['versions' => function ($query) {$query->orderBy('modversions.updated_at', 'desc');}])->get();
        return view('mod.create')->with(['mods' => $mods]);
    }

    /**
     * This confirms the upload and creates a zip with the correct format inside the storage/app/public/repo folder
     *
     * @return void
     */
    public function postUploadConfirm()
    {
        Log::debug("Confirming Upload");
        $rules = [
            'filename' => 'required',
            'modid' => 'required',
            'modversion' => 'required'
        ];
        $messages = [
            'filename.required' => 'The filename is required.',
            'modid.required' => 'The Mod-ID is required.',
            'modversion.required' => 'The Mod-Version is required'
        ];
        $validation = Validator::make(Request::all(), $rules, $messages);
        if ($validation->fails()) {
            Log::debug("Confirmation failed because of the following validation errors" . json_encode($validation->messages()));
            return response()->json(['status' => "error", 'reason' => 'check your inputs.', 'errors' => $validation->messages()]);
        } else {
            $filename = Request::input('filename');
            $modid = Request::input('modid');
            $modversion = Request::input('modversion');
            $this->createNewZippedModFile($filename, $modid, $modversion);
            //delete tmpfile:
            $deletePath = storage_path().'/app/modstmp/'.$filename;
            unlink($deletePath);
            //Check if mod already exists, if so return its data:
            $mod = Mod::where('name', $modid)->first();
            return response()->json(['status' => 'success', 'data' => [$mod]]);
        }
    }

    public function postCreate()
    {
        Log::debug("Creating new mod");
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
            Log::debug("Creation failed because of the following validation errors" . json_encode($validation->messages()));
            //return redirect('mod/create')->withErrors($validation->messages());
            return response()->json(['status' => "error", 'reason' => 'check your inputs.', 'errors' => $validation->messages()]); //dont lose uploads
        }

        $mod = new Mod();
        $mod->name = Str::slug(Request::input('name'));
        $mod->pretty_name = Request::input('pretty_name');
        $mod->author = Request::input('author');
        $mod->description = Request::input('description');
        $mod->link = Request::input('link');
        $mod->save();
        Log::debug("Successfully saved the new Mod as ".$mod->name);
        return response()->json(['status' => 'success', 'data' => ['id' => $mod->id]]);
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
        $mod->link = Request::input('link');
        //The following are not required by the validator, so we check for them first.
        if (Request::has('author')) {
            $mod->author = Request::input('author');
        }
        if (Request::has('description')) {
            $mod->description = Request::input('description');
        }
        if (Request::has('type')) {
            $mod->type = Request::input('type');
        }
        if (Request::has('side')) {
            $mod->side = Request::input('side');
        }
        $mod->save();
        Cache::forget('mod:' . $mod->name);

        return redirect('mod/view/' . $mod->id)->with('success', 'Mod successfully edited.');
    }

    public function postModifyVersion($version_id = null)
    {
        $rules = [
            'version' => 'required',
            'mcversion' => 'required',
            'loader' => 'required',
        ];

        $messages = [
            'version.required' => 'You must provide a version for the mod',
            'mcversion.required' => 'You must provide a Minecraft-Version',
            'loader.required' => 'You must provide a Valid launcher',
        ];

        $validation = Validator::make(Request::all(), $rules, $messages);
        if ($validation->fails()) {
            return redirect('mod/list')->withErrors($validation->messages());
        }

        if (empty($version_id)) {
            return response()->json([
                'status' => 'error',
                'reason' => 'Missing Post Data'
            ]);
        }

        $ver = Modversion::find($version_id);
        if (empty($ver)) {
            return response()->json([
                'status' => 'error',
                'reason' => 'Could not pull mod version from database'
            ]);
        }

        $ver->version = Request::input('version');
        $ver->mcversion = Request::input('mcversion');
        $ver->loader = Request::input('loader');
        $ver->save();
        return response()->json([
            'status' => 'success',
            'version' => Request::input('version'),
        ]);
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

    /**
     * Recieves an upload from user and saves it into an temp dir.
     * Sends info from mcmod.info back to client.
     * Client has to send a /mod/create or /mod/add-version request for every mod it uploaded.
     *
     * @return Response
     */
    public function postUpload()
    {
        Log::debug('Client is uploading a file.');
        $file = Request::file('file');
        $clientFilename = $file->getClientOriginalName();
        $workingFilename = str_replace(['+'], ['-'], $clientFilename); //replace problematic chars in filename
        $ulFileTmpPath = 'modstmp/'.$workingFilename;
        $resArr = [
            //default values:
            'success' => false,
            'newMod' => false,
            'newVersion' => false,
            'modInfo' => false,
            'uploadedFile' => $workingFilename,
            'error' => [
                'message' => ''
            ],
            'format' => 'jar'
        ];
        Log::debug('Client uploaded file ' . $workingFilename);
        Storage::disk('local')->putFileAs('modstmp/', $file, $workingFilename);
        Log::debug('Saved file as '.$ulFileTmpPath);
        $resArr['success'] = true; //mod was uploaded successfully

        if (!$modInfo = $this->readModInfo($workingFilename)) {
            Log::error('Could not read mod-info from ' . $workingFilename . '.');
            $resArr['error']['message'] = 'Could not load mod Info.';
            return response()->json($resArr);
        }

        //validate info:
        if (!$modInfo = $this->validateModInfo($modInfo)) {
            Log::warning($workingFilename . ' did not contain a mcmod.info file to read details out of.');
            $resArr['error']['message'] = 'Validation of mcmod.info failed.';
            return response()->json($resArr);
        } else {
            //mod has successfully uploaded and saved. mcmod.info has been located.
            $resArr['modInfo'] = $modInfo;
            //Check if mod with same slug already exists:
            $mod = Mod::find($modInfo->modid);
            if (empty($mod)) {
                //new mod
                $resArr['newMod'] = true;
                return response()->json($resArr);
            } else {
                //mod already exists.
                //check version if supplied:
                if (Modversion::where(['mod_id' => $modInfo->modid, 'version' => $modInfo->version])->count() > 0) {
                    //Mod with same version already exists in db.
                    return response()->json($resArr);
                } else {
                    //Version not found.
                    $resArr['newVersion'] = true;
                    return response()->json($resArr);
                }
            }
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
        $mcversion = Request::input('mcversion');
        $loader = Request::input('loader');

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

        //check if loader is an expected value:
        if (!in_array($loader, ['forge', 'fabric'])) {
            $loader = 'forge'; //just default to forge
        }

        $ver = new Modversion();
        $ver->mod_id = $mod->id;
        $ver->version = $version;
        $ver->mcversion = $mcversion;
        $ver->loader = $loader;
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

    /**
     * Reads the mcmod.info file from a provided .jar file.
     *
     * @param [type] $filename
     * @return array|bool
     */
    private function readModInfo($filename)
    {
        //Open as Zip file.
        $zip = new ZipArchive;
        $mcmodInfo = [false];
        $res = $zip->open(storage_path().'/app/modstmp/'.$filename, ZipArchive::RDONLY);
        if ($res === true) {
            if ($manifestIndex = $zip->locateName('mcmod.info', ZipArchive::FL_NOCASE|ZipArchive::FL_NODIR)) {
                Log::debug("Found JSON Format mcmod.info.");
                //get content (Will be in json):
                $mcModInfoContent = $zip->getFromIndex($manifestIndex);
                Log::debug("Raw Content: ".$mcModInfoContent);
                $mcmodInfo = json_decode($mcModInfoContent);
                if (is_object($mcmodInfo)) {
                    $mcmodInfo->{'INFOTYPE'} = 'mcmod.info';
                } else {
                    $mcmodInfo['INFOTYPE'] = 'mcmod.info';
                }
            } elseif ($manifestIndex = $zip->locateName('mods.toml', ZipArchive::FL_NOCASE|ZipArchive::FL_NODIR)) {
                Log::debug("Found TOML Format mods.toml");
                //get content (till be a toml file format):
                $mcModInfoContent = $zip->getFromIndex($manifestIndex);
                Log::debug("Raw Content: ".$mcModInfoContent);
                $mcmodInfo = Toml::Parse($mcModInfoContent);
                $mcmodInfo['INFOTYPE'] = 'mods.toml';
            }
            $zip->close();
        } else {
            $mcmodInfo = false;
        }
        return $mcmodInfo;
    }

    /**
     * Validates and fills missing info according to: https://mcforge.readthedocs.io/en/1.13.x/gettingstarted/structuring/#keeping-your-code-clean-using-sub-packages
     *
     * @param [type] $modInfo
     * @return object|boolean
     */
    private function validateModInfo($modInfo)
    {
        $info = array();
        /**
         * Check which type of mcmod.info this is.
         *
         * Known types:
         * [{modList->[{infos}]}]
         * {infos}
         * {pack->{infos}}
         * TOML Type: 
         */
        Log::debug("ModInfo: ".json_encode($modInfo));
        if (is_array($modInfo) && $modInfo['INFOTYPE'] == "mods.toml") {
            //TOML
            //$modInfo = $modInfo->mods[0]; //This should always be the norm. mod we are interested in should always be index 0.
            //Dependencies are here: $modInfo->dependencies. currently not used.
            $info = $this->parseModsTomlContents($modInfo);
        } elseif (is_object($modInfo) && $modInfo->INFOTYPE == "mcmod.info") {
            if (isset($modInfo->modList)) {
                if (is_array($modInfo->modList)) {
                    $modInfo = $modInfo->modList[0];
                }
            }
            if (isset($modInfo->pack)) {
                $modInfo = $modInfo->pack;
            }
            $info = $this->parseMcmodInfoContents($modInfo);
        } elseif (is_array($modInfo) && $modInfo['INFOTYPE'] == "mcmod.info") {
            if (is_object($modInfo[0])) {
                $modInfo = $modInfo[0];
            }
            $info = $this->parseMcmodInfoContents($modInfo);
        }
        $info['modid'] = Str::slug($info['modid']);
        Log::debug('Validated mod-Info: ', $info);
        return (object)$info;
    }

    /**
     * Parses the contents of the mcmod.info format.
     *
     * @param object $contents contents of the file. retrieved as object.
     * @return array|bool returns an array or a boolean if operation fails.
     */
    private function parseMcmodInfoContents($contents)
    {
        $info = [];
        //check if important infos are set and if not, set default value:
        if (!isset($contents->modid)) {
            return false; //without modid, the info is not really usable.
        } else {
            //generate mod slug:
            $info['modid'] = Str::slug($contents->modid);
        }
        $info['name'] = $contents->name ?? 'no pretty name given.'; //pretty name. required
        $info['description'] = $contents->description ?? 'no description given'; //description of the mod.
        $info['version'] = $contents->version ?? 'no mod version given.'; //version of the mod
        $info['mcversion'] = $contents->mcversion ?? 'no minecraft version given.'; //version of mc this version of the mod works on. ~ can be 1.12.x || 1.12.2  || 1.12.1-1.13.1 or any variation
        $info['url'] = $contents->url ?? ''; //shows url of author? OPTIONAL!
        $info['updateUrl'] = $contents->updateUrl ?? ''; //link to a url with versions listed.
        $info['updateJson'] = $contents->updateJson ?? 'no updatejson url given.'; //link to a json "file" with versions listed.
        $info['authorList'] = $contents->authorList ?? ['no author list provided.']; //Array of persons that authored this mod.
        if (!isset($contents->authorList) && isset($contents->authors)) {
            $info['authorList'] = $contents->authors; //many authors use this instead of the official way.
        }
        $info['credits'] = $contents->credits ?? 'no credits given.'; //credits? idk OPTIONAL!
        $info['logoFile'] = $contents->logoFile ?? 'no logo file path provided.'; //If the author included an logo, it will be referenced here.
        $info['screenshots'] = $contents->screenshots ?? ['no screenshot urls provided']; //Screenshots of the mod. OPTIONAL!
        $info['parent'] = $contents->parent ?? 'no parent id provided'; //id of the parent mod. for example used in modular mods as Buildcraft or mekanism.
        $info['useDependencyInformation'] = $contents->useDependencyInformation ?? false; //if true, the next three dependency args should be used.
        $info['requiredMods'] = $contents->requiredMods ?? ['no requirements provided.']; //A list of modids. If one is missing, the game will crash.
        $info['dependencies'] = $contents->dependencies ?? ['no dependencies provided']; //A list of modids. All of the listed mods will load before this one. If one is not present, nothing happens.
        if (!isset($contents->dependencies) && isset($contents->dependancies)) {
            $info['dependencies'] = $contents->dependancies; //spelling mistake. observed in ichuns backtools v4.0.0 (default mod included in solder.)
        }
        $info['dpendants'] = $contents->dpendants ?? ['no dependants provided.']; //A list of modids. All of the listed mods will load after this one. If one is not present, nothing happens.
        //send filled info back
        return $info;
    }

    /**
     * Parses the contents of the mods.toml format.
     *
     * @param object $contents contents of the file. retrieved as object.
     * @return array|bool returns an array or a boolean if operation fails.
     */
    private function parseModsTomlContents($contents)
    {
        $info = [];
        $mcDependency = [];
        $mods = $contents['mods'];
        $targetMod = $mods[0];
        $modId = $targetMod['modId'];
        if (!isset($contents['dependencies']) || !isset($contents['dependencies'][$modId])) {
            $dependencies = [];
        } else {
            $dependencies = $contents['dependencies'][$modId];
            //check dependencies for minecraft definition:
            foreach ($dependencies as $index => $dependency) {
                $dependencyId = $dependency['modId'];
                if ($dependencyId == "minecraft") {
                    $mcDependency = $dependencies[$index];
                    $mcDependency['versionRange'] = str_replace(['[',']','(',')'], [''], $mcDependency['versionRange']);
                    $mcDependency['versionRange'] = trim($mcDependency['versionRange'], ',');
                }
            }
        }
        

        //Arrayify Authors:
        if (isset($contents['authors']) && !isset($targetMod['authors'])) {
            $targetMod['authors'] = $contents['authors'];
        } elseif (!isset($contents['authors']) && !isset($targetMod['authors'])) {
            $targetMod['authors'] = 'none,defined';
        }
        if (strpos($targetMod['authors'], ',')) {
            $targetMod['authors'] = explode(',', $targetMod['authors']);
        } else {
            $targetMod['authors'] = [$targetMod['authors']];
        }

        $info['modid'] = $modId;
        $info['name'] = $targetMod['displayName'] ?? 'no pretty name given.'; //pretty name. required
        $info['description'] = $targetMod['description'] ?? 'no description given'; //description of the mod.
        $info['version'] = $targetMod['version'] ?? 'no mod version given.'; //version of the mod
        $info['mcversion'] =  $mcDependency['versionRange'] ?? 'no minecraft version given.'; //version of mc this version of the mod works on. ~ can be 1.12.x || 1.12.2  || 1.12.1-1.13.1 or any variation
        $info['url'] = $targetMod['displayURL'] ?? ''; //shows url of author? OPTIONAL!
        $info['updateUrl'] = $targetMod['updateJSONURL'] ?? ''; //link to a url with versions listed.
        $info['updateJson'] = $targetMod['updateJSONURL'] ?? 'no updatejson url given.'; //link to a json "file" with versions listed.
        $info['authorList'] = $targetMod['authors'] ?? ['no author list provided.']; //Array of persons that authored this mod.
        $info['credits'] = $targetMod['credits'] ?? 'no credits given.'; //credits? idk OPTIONAL!
        $info['logoFile'] = $targetMod['logoFile'] ?? 'no logo file path provided.'; //If the author included an logo, it will be referenced here.
        $info['screenshots'] = $targetMod['screenshots'] ?? ['no screenshot urls provided']; //Screenshots of the mod. OPTIONAL!
        $info['parent'] = $targetMod['parent'] ?? 'no parent id provided'; //id of the parent mod. for example used in modular mods as Buildcraft or mekanism.
        $info['useDependencyInformation'] = $targetMod['useDependencyInformation'] ?? false; //if true, the next three dependency args should be used.
        $info['requiredMods'] = $targetMod['requiredMods'] ?? ['no requirements provided.']; //A list of modids. If one is missing, the game will crash.
        $info['dependencies'] = $dependencies ?? ['no dependencies provided']; //A list of modids. All of the listed mods will load before this one. If one is not present, nothing happens.

        return $info;
    }

    /**
     * Creates the Version-Archive and moves it to the repo.
     *
     * @param string $clientFilename name of the uploaded file
     * @param string $modId          ID of the mod (Slug)
     * @param string $modVersion     Version of the mod
     * @return void
     */
    private function createNewZippedModFile($tempFilename, $modId, $modVersion)
    {
        //create mod zip file! move to app/public/mods/modslug/modslug-version.zip
        $newFileName = $modId.'-'.$modVersion.'.zip';
        $newFileTempPath = 'modstmp/'.$newFileName;
        $newFileFullTempPath = storage_path().'/app/'.$newFileTempPath;
        $newFilePubPath = 'public/mods/'.$modId.'/'.$newFileName;
        $newFileFullPubPath = storage_path().'/app/'.$newFilePubPath;
        Log::debug('Creating new zip file as modstmp/' . $newFileName);
        $newZipFile = new ZipArchive;
        //check if the file already exists. if yes, delete it.
        if (Storage::exists($newFileTempPath)) {
            //$res = $newZipFile->open('/var/www/storage/app/modstmp/'.$newFileName, ZipArchive::OVERWRITE);
            Storage::delete($newFileTempPath);
        }
        $res = $newZipFile->open($newFileFullTempPath, ZipArchive::CREATE);
        if ($res !== true) {
            //Could not create zipfile:
            Log::error('Could not create zipfile modstmp/' . $newFileName);
            return false;
            //Add mods/ folder:
        }
        if ($newZipFile->addEmptyDir('mods')) {
            //add the file now.
            if ($newZipFile->addFile(storage_path().'/app/modstmp/'.$tempFilename, 'mods/'.$tempFilename)) {
                //Add successfull, close archive.
                $newZipFile->close();
                //now move new file.
                if (Storage::exists($newFilePubPath)) {
                    Storage::delete($newFilePubPath);
                }
                Storage::move($newFileTempPath, $newFilePubPath);

                //return proposed data for new mod, or add version?
                return true;
            }
        } else {
            Log::error('Could not create folder inside zip file: modstmp/'. $newFileName);
            //could not create folder. what do?
            return false;
        }
    }
}
