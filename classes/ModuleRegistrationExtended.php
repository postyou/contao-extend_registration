<?php

namespace postyou;

class ModuleRegistrationExtended extends \ModuleRegistration
{
    protected function createNewUser($arrData)
    {

        $arrData['tstamp'] = time();
        $arrData['login'] = $this->reg_allowLogin;
        $arrData['activation'] = md5(uniqid(mt_rand(), true));
        $arrData['dateAdded'] = $arrData['tstamp'];
        $pw = $this->getRandomPassword(6);
        $arrData['password'] = \Encryption::hash($pw["clear"]);
        $arrData['username'] = strtolower($arrData['email']);
        $arrData['email'] = strtolower($arrData['email']);
        // Set default groups
        if (!array_key_exists('groups', $arrData)) {
            $arrData['groups'] = $this->reg_groups;
        }

//        // Disable account
//        $arrData['disable'] = 1;

        // Send activation e-mail
        if ($this->reg_activate) {
            $arrChunks = array();

            $strConfirmation = $this->reg_text;
            preg_match_all('/##[^#]+##/', $strConfirmation, $arrChunks);

            foreach ($arrChunks[0] as $strChunk) {
                $strKey = substr($strChunk, 2, -2);

                switch ($strKey) {
                    case 'domain':
                        $strConfirmation = str_replace($strChunk, \Idna::decode(\Environment::get('host')), $strConfirmation);
                        break;

                    case 'gen_pw':
                        $strConfirmation = str_replace($strChunk, $pw["clear"], $strConfirmation);
                        break;
                    case 'link':
                        $strConfirmation = str_replace($strChunk, \Idna::decode(\Environment::get('base')) . \Environment::get('request') . ((\Config::get('disableAlias') || strpos(\Environment::get('request'), '?') !== false) ? '&' : '?') . 'token=' . $arrData['activation'], $strConfirmation);
                        break;

                    // HOOK: support newsletter subscriptions
                    case 'channel':
                    case 'channels':
                        if (!in_array('newsletter', \ModuleLoader::getActive())) {
                            break;
                        }

                        // Make sure newsletter is an array
                        if (!is_array($arrData['newsletter'])) {
                            if ($arrData['newsletter'] != '') {
                                $arrData['newsletter'] = array($arrData['newsletter']);
                            } else {
                                $arrData['newsletter'] = array();
                            }
                        }

                        // Replace the wildcard
                        if (!empty($arrData['newsletter'])) {
                            $objChannels = \NewsletterChannelModel::findByIds($arrData['newsletter']);

                            if ($objChannels !== null) {
                                $strConfirmation = str_replace($strChunk, implode("\n", $objChannels->fetchEach('title')), $strConfirmation);
                            }
                        } else {
                            $strConfirmation = str_replace($strChunk, '', $strConfirmation);
                        }
                        break;

                    default:
                        $strConfirmation = str_replace($strChunk, $arrData[$strKey], $strConfirmation);
                        break;
                }
            }

            $objEmail = new \Email();

            $objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];
            $objEmail->fromName = $GLOBALS['TL_ADMIN_NAME'];
            $objEmail->subject = sprintf($GLOBALS['TL_LANG']['MSC']['emailSubject'], \Idna::decode(\Environment::get('host')));
            $objEmail->text = $strConfirmation;
            $objEmail->sendTo($arrData['email']);
        }

        // Make sure newsletter is an array
        if (isset($arrData['newsletter']) && !is_array($arrData['newsletter'])) {
            $arrData['newsletter'] = array($arrData['newsletter']);
        }

        // Create the user
        $objNewUser = new \MemberModel();
        $objNewUser->setRow($arrData);
        $objNewUser->save();

        $insertId = $objNewUser->id;

        // Assign home directory
        if ($this->reg_assignDir) {
            $objHomeDir = \FilesModel::findByUuid($this->reg_homeDir);

            if ($objHomeDir !== null) {
                $this->import('Files');
                $strUserDir = standardize($arrData['username']) ?: 'user_' . $insertId;

                // Add the user ID if the directory exists
                while (is_dir(TL_ROOT . '/' . $objHomeDir->path . '/' . $strUserDir)) {
                    $strUserDir .= '_' . $insertId;
                }

                // Create the user folder
                new \Folder($objHomeDir->path . '/' . $strUserDir);
                $objUserDir = \FilesModel::findByPath($objHomeDir->path . '/' . $strUserDir);

                // Save the folder ID
                $objNewUser->assignDir = 1;
                $objNewUser->homeDir = $objUserDir->uuid;
                $objNewUser->save();
            }
        }

        // HOOK: send insert ID and user data
        if (isset($GLOBALS['TL_HOOKS']['createNewUser']) && is_array($GLOBALS['TL_HOOKS']['createNewUser'])) {
            foreach ($GLOBALS['TL_HOOKS']['createNewUser'] as $callback) {
                $this->import($callback[0]);
                $this->$callback[0]->$callback[1]($insertId, $arrData, $this);
            }
        }

        // Inform admin if no activation link is sent
        if (!$this->reg_activate) {
            $this->sendAdminNotification($insertId, $arrData);
        }

        // Check whether there is a jumpTo page
        if (($objJumpTo = $this->objModel->getRelated('jumpTo')) !== null) {
            $this->jumpToOrReload($objJumpTo->row());
        }

        $this->reload();
    }




//    protected function sendAdminNotification($intId, $arrData)
//    {
//        // overwrite parent method
//    }


//    /*
//     * Sourcecode copied from Extension xtmembers // compatibility
//     * @copyright  Helmut Schottmüller 2008
//     * @author     Helmut Schottmüller <helmut.schottmueller@aurealis.de>
//     *
//     */
    public function getGroupSelection()
    {
        $this->loadLanguageFile('tl_module');
        $groups = array("" => $GLOBALS['TL_LANG']['tl_module']['reg_select_group']);
        if (strlen($this->groupselection_groups)) {
            $allowed_groups = deserialize($this->groupselection_groups, TRUE);
            if (is_array($allowed_groups) && count($allowed_groups) > 0) {
                $objGroup = $this->Database->prepare("SELECT * FROM tl_member_group WHERE id IN (" . implode(",", $allowed_groups) . ")")
                    ->execute();
                if ($objGroup->numRows >= 1) {
                    while ($objGroup->next()) {
                        $groups[$objGroup->id] = $objGroup->name;
                    }
                }
            }
        }
        return $groups;
    }

    /*
     *
     */
    protected function getRandomPassword($length = 8)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#_-+.";
        $password = substr(str_shuffle($chars), 0, $length);

        $strSalt = substr(md5(uniqid(mt_rand(), true)), 0, 23);
        $crypt = sha1($strSalt . $password) . ':' . $strSalt;

        return array('clear' => $password, 'crypt' => $crypt);
    }


}

?>