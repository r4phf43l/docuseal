<?php

include ("../../../inc/includes.php");

include_once(Plugin::getPhpDir('docuseal') . "/inc/apiclient.class.php");

// get the webhook
$raw_payload = file_get_contents('php://input', true);
$payload = json_decode($raw_payload, true);

if ($payload['event_type'] === 'form.completed') {
    
    $id = json_encode($payload['data']['submission']['id']);
    $url = $payload['data']['documents'][0]['url'];
    $comment = $payload['data']['values'][5]['value'];

    $apiclient = new DocusealAPIClient([
        'upload_url' => 1
    ]);

    $apiclient->initApi();
    $apiclient->saveSignedFile($id, $url, $comment);
}
