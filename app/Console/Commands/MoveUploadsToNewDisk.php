<?php

namespace App\Console\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MoveUploadsToNewDisk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snipeit:move-uploads {delete_local?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This will move your uploaded files to whatever your current disk is.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        if (config('filesystems.default')=='local') {
            $this->error('Your current disk is set to local so we cannot proceed.');
            $this->warn("Please configure your .env settings for S3 or Rackspace, \nand change your FILESYSTEM_DISK value to 's3' or 'rackspace'.");
            return false;
        }
        $delete_local = $this->argument('delete_local');

        $public_uploads['accessories'] = glob('storage/app/public/accessories'."/*.*");
        $public_uploads['assets'] = glob('storage/app/public/assets'."/*.*");
        $public_uploads['avatars'] = glob('storage/app/public/avatars'."/*.*");
        $public_uploads['barcodes'] = glob('storage/app/public/barcodes'."/*.*");
        $public_uploads['categories'] = glob('storage/app/public/categories'."/*.*");
        $public_uploads['companies'] = glob('storage/app/public/companies'."/*.*");
        $public_uploads['components'] = glob('storage/app/public/components'."/*.*");
        $public_uploads['consumables'] = glob('storage/app/public/consumables'."/*.*");
        $public_uploads['departments'] = glob('storage/app/public/departments'."/*.*");
        $public_uploads['locations'] = glob('storage/app/public/locations'."/*.*");
        $public_uploads['manufacturers'] = glob('storage/app/public/manufacturers'."/*.*");
        $public_uploads['suppliers'] = glob('storage/app/public/suppliers'."/*.*");
        $public_uploads['assetmodels'] = glob('storage/app/public/models'."/*.*");


        // iterate files
        foreach($public_uploads as $public_type => $public_upload)
        {
            $type_count = 0;
            $this->info("\nThere are ".count($public_upload).' PUBLIC '.$public_type.' files.');

            for ($i = 0; $i < count($public_upload); $i++) {
                $type_count++;
                $filename = basename($public_upload[$i]);

                try  {
                    Storage::disk('public')->put($public_type.'/'.$filename, file_get_contents($public_upload[$i]));
                    $new_url = Storage::disk('public')->url($public_type.'/'.$filename, $filename);
                    $this->info($type_count.'. PUBLIC: '.$filename.' was copied to '.$new_url);
                } catch (\Exception $e) {
                    \Log::debug($e);
                    $this->error($e);
                }

            }

        }

        $logos = glob('public/uploads'."/logo*.*");
        $this->info("\nThere are ".count($logos).' files that might be logos.');
        $type_count=0;

        for ($l = 0; $l < count($logos); $l++) {
            $type_count++;
            $filename = basename($logos[$l]);
            $new_url = Storage::disk('public')->url($logos[$l], file_get_contents($public_upload[$i]));
            $this->info($type_count.'. LOGO: '.$filename.' was copied to '.$new_url);
        }

        $private_uploads['assets'] = glob('storage/private_uploads/assets'."/*.*");
        $private_uploads['signatures'] = glob('storage/private_uploads/signatures'."/*.*");
        $private_uploads['audits'] = glob('storage/private_uploads/audits'."/*.*");
        $private_uploads['assetmodels'] = glob('storage/private_uploads/assetmodels'."/*.*");
        $private_uploads['imports'] = glob('storage/private_uploads/imports'."/*.*");
        $private_uploads['licenses'] = glob('storage/private_uploads/licenses'."/*.*");
        $private_uploads['users'] = glob('storage/private_uploads/users'."/*.*");


        foreach($private_uploads as $private_type => $private_upload)
        {
            $this->info("\nThere are ".count($private_upload).' PRIVATE '.$private_type.' files.');
            // $this->info(print_r($private_upload, true));

            $type_count = 0;
            for ($x = 0; $x < count($private_upload); $x++) {
                $type_count++;
                $filename = basename($private_upload[$x]);

                try  {
                    Storage::disk('private_uploads')->put($private_type.'/'.$filename, file_get_contents($public_upload[$i]));
                    $new_url = Storage::url($private_type.'/'.$filename, $filename);
                    $this->info($type_count.'. PRIVATE: '.$filename.' was copied to '.$new_url);

                } catch (\Exception $e) {
                    \Log::debug($e);
                    $this->error($e);
                }

            }

        }


        if ($delete_local=='true') {
            $public_delete_count = 0;
            $private_delete_count = 0;

            $this->info("\n\n");
            $this->error('!!!!!!!!!!!!!!!!!!!!!!!!!!!!! WARNING!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');
            $this->warn("\nTHIS WILL DELETE ALL OF YOUR LOCAL UPLOADED FILES. \n\nThis cannot be undone, so you should take a backup of your system before you proceed.\n");
            $this->error('!!!!!!!!!!!!!!!!!!!!!!!!!!!!! WARNING!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');

            if ($this->confirm("Do you wish to continue?")) {

                foreach($public_uploads as $public_type => $public_upload) {

                    for ($i = 0; $i < count($public_upload); $i++) {
                        $filename = $public_upload[$i];
                        try {
                            unlink($filename);
                            $public_delete_count++;
                        } catch (\Exception $e) {
                            \Log::debug($e);
                            $this->error($e);
                        }

                    }
                }

                foreach($private_uploads as $private_type => $private_upload)
                {

                    for ($i = 0; $i < count($private_upload); $i++) {
                        $filename = $private_upload[$i];
                        try {
                            unlink($filename);
                            $private_delete_count++;
                        } catch (\Exception $e) {
                            \Log::debug($e);
                            $this->error($e);
                        }

                    }
                }

                $this->info($public_delete_count." PUBLIC local files and ".$private_delete_count." PRIVATE local files were deleted from your filesystem.");
            }
        }




    }
}
