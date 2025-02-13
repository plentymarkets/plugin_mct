<?php

use League\Flysystem\Filesystem;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;


$adapter = new SftpAdapter(
    new SftpConnectionProvider(
        SdkRestApi::getParam('host'), // host (required)
        SdkRestApi::getParam('username'), // username (required)
        SdkRestApi::getParam('password'), // password (optional, default: null) set to null if privateKey is used
        SdkRestApi::getParam('privatekey'), // private key (optional, default: null) can be used instead of password, set to null if password is set
        null, // passphrase (optional, default: null), set to null if privateKey is not used or has no passphrase
        22, // port (optional, default: 22)
        false, // use agent (optional, default: false)
        30, // timeout (optional, default: 10)
        10, // max tries (optional, default: 4)
        null, // host fingerprint (optional, default: null),
        null, // connectivity checker (must be an implementation of 'League\Flysystem\PhpseclibV2\ConnectivityChecker' to check if a connection can be established (optional, omit if you don't need some special handling for setting reliable connections)
    ),
    '/', // root path (required)
    PortableVisibilityConverter::fromArray([
        'file' => [
            'public' => 0640,
            'private' => 0604,
        ],
        'dir' => [
            'public' => 0740,
            'private' => 7604,
        ],
    ])
);

$filesystem = new Filesystem($adapter);

$folderPath = './';
if (SdkRestApi::getParam('folderPath') !== ''){
    $folderPath .= SdkRestApi::getParam('folderPath') . '/';
}

try {
    $filesystem->write($folderPath . SdkRestApi::getParam('fileName'),
        SdkRestApi::getParam('xmlContent'), []);
    return [
        'error' => false
    ];
} catch (FilesystemException | UnableToWriteFile $exception) {
    return [
        'error'     => true,
        'error_msg' => $exception->getMessage()
    ];
}