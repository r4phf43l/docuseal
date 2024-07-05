<?php

use Glpi\Api\APIRest;

class DocusealAPIClient extends APIRest
{
    private $iterator;
    private $config;
    private $defaultNameFile = 'docuseal_';

    public function initApi()
    {
        global $CFG_GLPI;
        $CFG_GLPI['enable_api'] = true;
        parent::initApi();

        include_once(Plugin::getPhpDir('docuseal') . '/inc/config.class.php');
        $this->config = new PluginDocusealConfig();
        $this->config->getFromDB(1);

        $this->defaultNameFile = $this->config->fields['default_filename'] !== '' ? $this->config->fields['default_filename'] . '_' : $this->defaultNameFile;

        // Initialize entities
        Session::initEntityProfiles($this->config->fields['system_user_id']);
        // use first profile
        Session::changeProfile(key($_SESSION['glpiprofiles']));
        $_SESSION['glpiname'] = 'apirest-docuseal';
    }

    public function saveSignedFile(string $uuid, string &$url, string &$comment)
    {
        $iterator = $this->getSigners($uuid);
        if (!count($iterator)) { return; }

        $signer = $iterator->current();

        $filename = $this->uploadFile($url);
        
        $doc = new Document();
        $data = [
            'filepath' => [GLPI_TMP_DIR . '/' . $filename],
            '_filename' => [$filename],
            'itemtype' => 'Ticket',
            'items_id' => $signer['ticket_id'],
            'tickets_id' => $signer['ticket_id'],
            'filename' => $filename,
            'mime' => 'application/pdf',
            'comment' => $comment . ' ' . $this->config->fields['default_accept_comment']
        ];
        $data['sha1sum'] = sha1_file(GLPI_TMP_DIR . '/' . $filename);
        $doc->add($data);
        $this->saveValidation($comment);
    }

    private function saveValidation($comment)
    {
        $validation = new TicketValidation();
        $signer = $this->iterator->current();
        do {
            $validation->update([
                'id' => $signer['validation_id'],
                'users_id_validate' => $signer['user_id'],
                'comment_validation' => $comment . ' ' . $this->config->fields['default_accept_comment'],
                'validation_date' => date('Y-m-d H:i:s'),
                'status' => CommonITILValidation::ACCEPTED
            ]);
        } while ($signer = $this->iterator->next());
    }

    private function getSigners($uuid)
    {
        global $DB;
        $request = [
            'SELECT' => [
                'glpi_plugin_docuseal_files.ticket_id',
                'glpi_plugin_docuseal_files.user_id',
                'glpi_ticketvalidations.id AS validation_id'
            ],
            'FROM' => 'glpi_plugin_docuseal_files',
            'INNER JOIN'   => [
                'glpi_ticketvalidations' => [
                    'ON' => [
                        'glpi_plugin_docuseal_files' => 'user_id',
                        'glpi_ticketvalidations' => 'users_id_validate'
                    ]
                ]
            ],
            'WHERE' => [
                'glpi_plugin_docuseal_files.file_uuid' => $uuid
            ]
        ];

        $this->iterator = $DB->request($request);

        if (!count($this->iterator)) {
            $this->returnError('Invalid UUID');
            return;
        }
        return $this->iterator;
    }

    private function uploadFile($url) {
        // Initialize a cURL session
        $ch = curl_init();

        // Set the URL
        curl_setopt($ch, CURLOPT_URL, $url);

        // Return the transfer as a string instead of outputting it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Execute the cURL session
        $fileContents = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            error_log('Error:' . curl_error($ch));
        }

        // Close the cURL session
        curl_close($ch);

        // Create a temporary file
        $tempFilePath = tempnam(GLPI_TMP_DIR, $this->defaultNameFile) . '.pdf';

        // Write the file contents to the temporary file
        file_put_contents($tempFilePath, $fileContents);

        $filename = basename($tempFilePath);

        return $filename;
    }
}
