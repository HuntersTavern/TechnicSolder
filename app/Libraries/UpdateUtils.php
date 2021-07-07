<?php

namespace App\Libraries;

use Github\Client;
use Illuminate\Support\Facades\Config;
use RuntimeException;

class UpdateUtils
{

    public static $githubclient;
    private static $org = 'technicpack';
    private static $repo = 'technicsolder';
    private static $branch = 'master';

    public static function init()
    {
        $client = new Client();
        // TODO: Re-add caching (it got removed upstream)
        self::$githubclient = $client;
        //Get Config for Org and Repo for updating
        self::$org = Config::get('solder.github_org');
        self::$repo = Config::get('solder.github_repo');
        self::$branch = Config::get('solder.github_branch');
    }

    public static function getUpdateCheck()
    {

        $allVersions = self::getAllVersions();

        if (!array_key_exists('error', $allVersions)) {
            if (version_compare(self::getLatestVersion()['name'], SOLDER_VERSION, '>')) {
                return true;
            }
        }

        return false;

    }

    public static function getLatestVersion()
    {

        $allVersions = self::getAllVersions();
        if (array_key_exists('error', $allVersions)) {
            return $allVersions;
        }
        return $allVersions[0];

    }

    public static function getAllVersions()
    {

        try {
            return self::$githubclient->api('repo')->tags(self::$org, self::$repo);
        } catch (RuntimeException $e) {
            return ['error' => 'Unable to pull version from Github - ' . $e->getMessage()];
        }

    }

    public static function getCommitInfo($commit = null)
    {

        if (is_null($commit)) {
            $commit = self::getLatestVersion()['commit']['sha'];
        }

        try {
            return self::$githubclient->api('repo')->commits()->show(self::$org, self::$repo, $commit);
        } catch (RuntimeException $e) {
            return ['error' => 'Unable to pull commit info from Github - ' . $e->getMessage()];
        }

    }

    public static function getLatestChangeLog()
    {

        try {
            return self::$githubclient->api('repo')->commits()->all(self::$org, self::$repo, ['sha' => self::$branch]);
        } catch (RuntimeException $e) {
            return ['error' => 'Unable to pull changelog from Github - ' . $e->getMessage()];
        }

    }
}
