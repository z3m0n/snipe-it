<?php
(PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('Access denied.');

$required_version = '7.1.3';

/*
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	echo "Skipping user check as it is not supported on Windows\n";
} else {
	$pwu_data = posix_getpwuid(posix_geteuid());
	$username = $pwu_data['name'];

	if (($username=='root') || ($username=='admin')) {
		die("\nERROR: This script should not be run as root/admin. Exiting.\n\n");
	}
}
*/

// Check if a branch or tag was passed in the command line,
// otherwise just use master
(array_key_exists('1', $argv)) ? $branch = $argv[1] : $branch = 'master';

echo "Welcome to the Snipe-IT upgrader.\n\n";
echo "Please note that this script will not download the latest Snipe-IT \n";
echo "files for you unless you have git installed. \n";
echo "It simply runs the standard composer and artisan \n";
echo "commands needed to finalize the upgrade after. \n\n";

echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! WARNING !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
echo "!! If you have any encrypted custom fields, BE SURE TO run the recrypter if upgrading from v3 to v4. \n";
echo "!! See the Snipe-IT documentation for help: \n";
echo "!! https://snipe-it.readme.io/docs/upgrading-to-v4\n";
echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! WARNING !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";

echo "--------------------------------------------------------\n";
echo "STEP 1: Checking PHP requirements: \n";
echo "--------------------------------------------------------\n\n";

echo "Current PHP version: " . PHP_VERSION . "\n\n";

if (version_compare(PHP_VERSION, $required_version, '<')) {
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! ERROR !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
    echo "This version of PHP is not compatible with Snipe-IT.\n";
    echo "Snipe-IT requires PHP version ".$required_version." or greater. Please upgrade \n";
    echo "your server's version of PHP (mod and cli) and try running this script again.\n\n\n";
    exit;

} else {
    echo "PHP version: " . PHP_VERSION . " is at least ".$required_version." - continuing... \n\n";
}


echo "--------------------------------------------------------\n";
echo "STEP 2: Backing up database: \n";
echo "--------------------------------------------------------\n\n";
$backup = shell_exec('php artisan snipeit:backup');
echo '-- '.$backup."\n\n";

echo "--------------------------------------------------------\n";
echo "STEP 3: Putting application into maintenance mode: \n";
echo "--------------------------------------------------------\n\n";
$down = shell_exec('php artisan down');
echo '-- '.$down."\n\n";


echo "--------------------------------------------------------\n";
echo "STEP 4: Pulling latest from Git (".$branch." branch): \n";
echo "--------------------------------------------------------\n\n";
$git_version = shell_exec('git --version');

if ((strpos('git version', $git_version)) === false) {
    echo "Git is installed. \n";
    $git_checkout = shell_exec('git checkout '.$branch);
    $git_stash = shell_exec('git stash');
    $git_pull = shell_exec('git pull');
    echo '-- '.$git_stash;
    echo '-- '.$git_checkout;
    echo '-- '.$git_pull;
} else {
    echo "Git is NOT installed. You can still use this upgrade script to run common \n";
    echo "migration commands, but you will have to manually download the updated files. \n\n";
}



echo "--------------------------------------------------------\n";
echo "Step 5: Cleaning up old cached files:\n";
echo "--------------------------------------------------------\n\n";


if (file_exists('bootstrap/cache/compiled.php')) {
    echo "-- Deleting bootstrap/cache/compiled.php. It is no longer used.\n";
    @unlink('bootstrap/cache/compiled.php');
} else {
    echo "-- No bootstrap/cache/compiled.php, so nothing to delete.\n";
}

if (file_exists('bootstrap/cache/services.php')) {
    echo "-- Deleting bootstrap/cache/services.php. It it no longer used.\n";
    @unlink('bootstrap/cache/services.php');
} else {
    echo "-- No bootstrap/cache/services.php, so nothing to delete.\n";
}

if (file_exists('bootstrap/cache/config.php')) {
    echo "-- Deleting bootstrap/cache/config.php. It it no longer used.\n";
    @unlink('bootstrap/cache/config.php');
} else {
    echo "-- No bootstrap/cache/config.php, so nothing to delete.\n";
}

$config_clear = shell_exec('php artisan config:clear');
$cache_clear = shell_exec('php artisan cache:clear');
$route_clear = shell_exec('php artisan route:clear');
$view_clear = shell_exec('php artisan view:clear');
echo '-- '.$config_clear;
echo '-- '.$cache_clear;
echo '-- '.$route_clear;
echo '-- '.$view_clear;
echo "\n";

echo "--------------------------------------------------------\n";
echo "Step 6: Updating composer dependencies:\n";
echo "(This may take an moment.)\n";
echo "--------------------------------------------------------\n\n";


// Composer install
if (file_exists('composer.phar')) {
    echo "-- Local composer.phar detected, so we'll use that.\n\n";
    $composer_dump = shell_exec('php composer.phar dump');
    $composer = shell_exec('php composer.phar install --no-dev --prefer-source');

} else {
    echo "-- We couldn't find a local composer.phar - trying globally.\n\n";
    $composer_dump = shell_exec('composer dump');
    $composer = shell_exec('composer install --no-dev --prefer-source');
}

echo $composer_dump."\n\n";
echo $composer."\n\n";


echo "--------------------------------------------------------\n";
echo "Step 7: Migrating database:\n";
echo "--------------------------------------------------------\n\n";

$migrations = shell_exec('php artisan migrate --force');
echo '-- '.$migrations."\n\n";


echo "--------------------------------------------------------\n";
echo "Step 8: Checking for OAuth keys:\n";
echo "--------------------------------------------------------\n\n";


if ((!file_exists('storage/oauth-public.key')) || (!file_exists('storage/oauth-private.key'))) {
    echo "- No OAuth keys detected. Running passport install now.\n\n";
    $passport = shell_exec('php artisan passport:install');
    echo $passport;
} else {
    echo "- OAuth keys detected. Skipping passport install.\n\n";
}


echo "--------------------------------------------------------\n";
echo "Step 9: Caching routes and config:\n";
echo "--------------------------------------------------------\n\n";
$config_cache = shell_exec('php artisan config:cache');
$route_cache = shell_exec('php artisan route:cache');
echo '-- '.$config_cache;
echo '-- '.$route_cache;
echo "\n";



echo "--------------------------------------------------------\n";
echo "Step 10: Taking application out of maintenance mode:\n";
echo "--------------------------------------------------------\n\n";

$up = shell_exec('php artisan up');
echo '-- '.$up."\n\n";


echo "--------------------------------------------------------\n";
echo "Step 11: Checking for v5 public storage directories:    \n";
echo "--------------------------------------------------------\n\n";


if ((!file_exists('storage/app/public')) && (!is_dir('storage/app/public'))) {
    echo "- No public directory found in storage/app - creating one.\n\n";
    if (!mkdir('storage/app/public', 0777, true)) {
        echo "ERROR: Failed to create directory at storage/app/public. You should do this manually.\n\n";
    }
    $storage_simlink = shell_exec('php artisan storage:link');
    echo $storage_simlink;

} else {
    echo "- Public storage directory already exists. Skipping...\n\n";
}

echo "- Copying files into storage/app/public.\n\n";
if (rmove('public/uploads','storage/app/public')) {
    echo "- Copy successful.\n\n";
} else {
    echo "- Copy failed - you should do this manually by copying the files from public/uploads into the storage/app/public directory.\n\n";
}

echo "--------------------------------------------------------\n";
echo "FINISHED! Clear your browser cookies and re-login to use :\n";
echo "your upgraded Snipe-IT.\n";
echo "--------------------------------------------------------\n\n";


/**
 * Recursively move files from one directory to another
 *
 * @param String $src - Source of files being moved
 * @param String $dest - Destination of files being moved
 */
function rmove($src, $dest){

    // If source is not a directory stop processing
    if(!is_dir($src)) return false;

    // If the destination directory does not exist create it
    if(!is_dir($dest)) {
        if(!mkdir($dest)) {
            // If the destination directory could not be created stop processing
            return false;
        }
    }

    // Open the source directory to read in files
    $i = new DirectoryIterator($src);
    foreach($i as $f) {
        if($f->isFile()) {
            rename($f->getRealPath(), "$dest/" . $f->getFilename());
        } else if(!$f->isDot() && $f->isDir()) {
            rmove($f->getRealPath(), "$dest/$f");
            unlink($f->getRealPath());
        }
    }
    unlink($src);
}
