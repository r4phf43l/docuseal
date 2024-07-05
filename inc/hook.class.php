<?php

use GuzzleHttp\Exception\BadResponseException;

class PluginDocusealHook extends CommonDBTM
{
    public static function itemUpdate(TicketValidation $ticket)
    {
        try {
            $ticket_id = isset($ticket->input['tickets_id']) ? $ticket->input['tickets_id'] : $ticket->fields['tickets_id'];
            $user_id = isset($ticket->input['users_id_validate']) ? $ticket->input['users_id_validate'] : $ticket->fields['users_id_validate'];
            $iterator = self::getSignRequests([
                'ticket_id' => $ticket_id,
                'user_id' => $user_id
            ]);

            if (!count($iterator)) {
                throw new Exception(t_docuseal('No signer found'));
            }
            
            foreach ($iterator as $row) {
                $signer['email'] = $row['email'];
                foreach ($ticket->updates as $field) {
                    switch ($field) {
                        case 'comment_submission':
                            $signer['description'] = $ticket->input[$field];
                            break;
                    }
                }
                if (count($signer) > 1) {
                    self::requestUpdateSigner(
                        $row['file_uuid'],
                        $signer,
                        $ticket
                    );
                }
            }
        } catch (\Exception $e) {
            $ticket->input = null;
            Session::addMessageAfterRedirect(
                sprintf(
                    t_docuseal('Failure on send update sign in DocuSeal. Error: %s.'),
                    $e->getMessage()
                ),
                false,
                ERROR
            );
            return;
        }
    }

    private static function requestUpdateSigner(string $id, array $signer, TicketValidation $ticket)
    {
        include_once(Plugin::getPhpDir('docuseal') . '/inc/config.class.php');
        $config = new PluginDocusealConfig();
        $config->getFromDB(1);

        include_once(Plugin::getPhpDir('docuseal') . '/inc/httpclient.class.php');
        $client = new PluginDocusealHttpclient();

        // delete old
        self::preItemPurge($ticket);

        // add new
        self::preItemAdd($ticket);
    }

    public static function preItemPurge(TicketValidation $ticket)
    {
        try {
            $user_id = isset($ticket->input['users_id_validate']) ? $ticket->input['users_id_validate'] : $ticket->fields['users_id_validate'];
            $iterator = self::getSignRequests([
                'ticket_id' => $ticket->fields['tickets_id'],
                'user_id' => $user_id
            ]);
            if (!count($iterator)) {
                throw new Exception(t_docuseal('No signer found'));
            }
            foreach ($iterator as $row) {
                self::requestDeleteSigner($row['file_uuid'], $row['email']);
                self::deleteRelation($row['file_uuid'], $ticket);
            }
            
        } catch (\Exception $e) {
            $ticket->input = null;
            Session::addMessageAfterRedirect(
                sprintf(
                    t_docuseal('Failure on send delete sign in DocuSeal. Error: %s.'),
                    $e->getMessage()
                ),
                false,
                ERROR
            );
            return;
        }
    }

    private static function requestDeleteSigner(string $id, string $email)
    {
        include_once(Plugin::getPhpDir('docuseal') . '/inc/config.class.php');
        $config = new PluginDocusealConfig();
        $config->getFromDB(1);

        include_once(Plugin::getPhpDir('docuseal') . '/inc/httpclient.class.php');
        $client = new PluginDocusealHttpclient();
        try {
            $url = str_replace('/emails', '/'. $id, $config->fields['docuseal_url']);
            $client->delete($url, [
                'headers' => [ 'X-Auth-Token' => $config->fields['token'] ]
            ]);
        } catch (BadResponseException $e) {
            $return = $e->getResponse()->getBody()->getContents();
            $json = json_decode($return);
            if ($json && $json->message) {
                throw new Exception(t_docuseal($json->message));
            }
            throw new Exception($return);
        }
    }

    private static function getSignRequests($filter)
    {
        global $DB;
        $return = $DB->request([
            'SELECT' => [
                'file.file_uuid',
                'file.user_id',
                'file.response_date',
                'glpi_useremails.email'
            ],
            'FROM' => 'glpi_plugin_docuseal_files AS file',
            'INNER JOIN' => [
                'glpi_useremails' => [
                    'ON' => [
                        'file' => 'user_id',
                        'glpi_useremails' => 'users_id'
                    ]
                ]
            ],
            'WHERE' => $filter
        ]);
        return $return;
    }

    public static function preItemAdd(TicketValidation $ticket)
    {
        try {
            if (strpos($_SERVER['REQUEST_URI'], 'ticketvalidation.form.php') === false) {
                return;
            }
            $user = new User();
            $user_id = isset($ticket->input['users_id_validate']) ? $ticket->input['users_id_validate'] : $ticket->fields['users_id_validate'];
            $user->getFromDB($user_id);
            $email = self::getUserEmail($user);

            include_once(Plugin::getPhpDir('docuseal') . '/inc/config.class.php');
            $config = new PluginDocusealConfig();
            $config->getFromDB(1);
            $displayName = self::getDisplayName($config, $user);

            $options = [
                'template_id' => $config->fields['template_id'],
                'order' => 'preserved',
                'submitters' => [
                    [
                        'email' => $email,
                        'fields' => [
                            [
                                'name' => 'user',
                                'default_value' => $displayName,
                                'readonly' => true
                            ],
                            [
                                'name' => 'solution',
                                'default_value' => strip_tags(html_entity_decode($ticket->input['comment_submission'])) ?: $config->fields['default_request_comment'],
                                'readonly' => true
                            ],
                            [
                                'name' => 'ticket_id',
                                'default_value' => $ticket->input['tickets_id'],
                                'readonly' => true
                            ],
                            [
                                'name' => 'email',
                                'default_value' => $email,
                                'readonly' => true
                            ],
                            [
                                'name' => 'tech',
                                'default_value' => getUserName($_SESSION['glpiID']),
                                'readonly' => true
                            ],
                            [
                                'name' => 'ticket_date',
                                'default_value' => date("Y-m-d"),
                                'readonly' => true
                            ]
                        ]
                    ]
                ]
            ];

            $iterator = self::getSignRequests([
                'ticket_id' => $ticket->input['tickets_id']
            ]);
            
            if (count($iterator)) {
                foreach ($iterator as $row) {
                    if ($row['response_date']) {
                        throw new Exception(t_docuseal(
                            'File already signed by %s, impossible to add another subscriber. ' .
                            'Delete all signers, the signed file and request new signatures.'
                        ));
                    }
                    if ($row['user_id'] == $user_id) {
                        throw new Exception(sprintf(
                            t_docuseal('Signature already requested for %s'),
                            $displayName
                        ));
                    }
                }

                $options['id'] = $row['file_uuid'];
                $method = 'POST';
            } else {
                $method = 'POST';
                $options['file'] = [
                    'base64' => base64_encode(self::getPdf($ticket))
                ];
            }
            $id = self::requestSign($method, $config, $options);
            self::insertRelation($id, $ticket);
        } catch (\Exception $e) {
            $ticket->input = null;
            Session::addMessageAfterRedirect(
                sprintf(
                    t_docuseal('Failure on send file to sign in DocuSeal. Error: %s.'),
                    $e->getMessage()
                ),
                false,
                ERROR
            );
             return;
        }
    }

    private static function getUserEmail(User $user)
    {
        $email = $user->getDefaultEmail();
        if (!$email) {
            throw new Exception(sprintf(
                t_docuseal('The selected user (%s) has no valid email address. The request has not been created.'),
                $user->getField('name')
            ));
        }
        return $email;
    }

    private static function getDisplayName(PluginDocusealConfig $config, User $user)
    {
        $displayName = $user->getField($config->fields['default_display_name']);
        if (!$displayName) {
            throw new Exception(sprintf(
                t_docuseal('The selected user (%s) has no valid %s. The request has not been created, without %s.'),
                $user->getField('name'),
                $config->fields['default_display_name'],
                $config->fields['default_display_name']
            ));
        }
        return $displayName;
    }

    private static function requestSign(string $method, PluginDocusealConfig $config, array $options)
    {
        include_once(Plugin::getPhpDir('docuseal') . '/inc/httpclient.class.php');
        $client = new PluginDocusealHttpclient();
        $response = $client->request($method, $config->fields['docuseal_url'], [
            'json' => $options,
            'headers' => [ 'X-Auth-Token' => $config->fields['token'] ]
        ]);
        $json = $response->getBody()->getContents();

        if (!$json) {
            throw new \Exception('Invalid response from DocuSeal');
        }
        $json = json_decode($json);
        if (!$json) {
            throw new \Exception('Invalid JSON from DocuSeal');
        }
        return $json[0]->id;
    }

    private static function insertRelation(string $id, TicketValidation $ticket)
    {
        global $DB;
        $user_id = isset($ticket->input['users_id_validate']) ? $ticket->input['users_id_validate'] : $ticket->fields['users_id_validate'];
        $insert = $DB->insert('glpi_plugin_docuseal_files', [
            'file_uuid' => $id,
            'user_id' => $user_id,
            'ticket_id' => $ticket->input['tickets_id'],
            'request_date' => date('Y-m-d H:i:s')
        ]);
    }

    private static function deleteRelation(string $id, TicketValidation $ticket)
    {
        global $DB;
        $user_id = isset($ticket->input['users_id_validate']) ? $ticket->input['users_id_validate'] : $ticket->fields['users_id_validate'];
        $DB->delete('glpi_plugin_docuseal_files', [
            'file_uuid' => $id,
            'user_id' => $user_id,
            'ticket_id' => $ticket->input['tickets_id']
        ]);
    }

    private static function getPdf(TicketValidation $ticketValidation)
    {
        global $PLUGIN_HOOKS;
        $ticket = new Ticket();
        $pdf = new $PLUGIN_HOOKS['plugin_pdf']['Ticket']($ticket);
        return $pdf->generatePDF([$ticketValidation->input['tickets_id']], ['Ticket$main'], 0, false);
    }
}
