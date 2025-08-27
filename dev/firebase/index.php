<?php

// ini_set('memory_limit', '512M');

require __DIR__.'/vendor/autoload.php';

use Google\Cloud\Firestore\FirestoreClient;

putenv("GOOGLE_APPLICATION_CREDENTIALS=".__DIR__."/key/test-php-controller-62fa8eeaea48.json");

$db = new FirestoreClient([
    'projectId' => "test-php-controller",
]);

$docRef = $db->collection('samples/php/users')->document('lovelace');

try{
    $docRef->set([
     'first' => 'Ada',
     'last' => 'Lovelace',
     'born' => 1815
    ]);
} catch (Exception $e){
    var_dump($e);
}


?>