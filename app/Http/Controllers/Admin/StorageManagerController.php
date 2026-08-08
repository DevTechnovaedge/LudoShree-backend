<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class StorageManagerController extends Controller
{

    public function index($path = 'public')
    {
        $basePath = $path; // Base directory in storage
        $directory = $basePath;
    
        // Get only the direct subdirectories and files in the current folder
        $disallowedFolders = ['profile', 'site', 'proof'];

        $folders = Storage::directories($directory);

        // Filter only allowed folders
        $folders = array_filter($folders, function ($folder) use ($disallowedFolders) {
          return !in_array(basename($folder), $disallowedFolders);
        });

        $disallowedFiles = ['.gitignore'];

        $files = Storage::files($directory);
        
        $files = array_filter($files, function ($file) use ($disallowedFiles) {
            return !in_array(basename($file), $disallowedFiles);
        });
        
        
        return view('admin.file-manger.index', compact('directory', 'folders', 'files', 'directory'));
    }
    
  
  

    public function delete()
    {
        if (request()->has('files')) {
            foreach (request()->files as $file) {
                Storage::delete($file);
            }
            return back()->with('success', 'Files deleted successfully.');
        }
        return back()->with('error', 'No files selected.');
    }
}
