<?php
namespace postyou;


class ModuleLoginExtended extends \ModuleLogin
{

    public function generate()
    {
        if (TL_MODE == 'BE') {
            $objTemplate = new \BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . utf8_strtoupper($GLOBALS['TL_LANG']['FMD']['login'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        // Set the last page visited
        if ($this->redirectBack) {
            $_SESSION['LAST_PAGE_VISITED'] = $this->getReferer();
        }

        // Login
        if (\Input::post('FORM_SUBMIT') == 'tl_login') {
            // Check whether username and password are set
            if (empty($_POST['username']) || empty($_POST['password'])) {
                $_SESSION['LOGIN_ERROR'] = $GLOBALS['TL_LANG']['MSC']['emptyField'];
                $this->reload();
            }

            $this->import('FrontendUser', 'User');
            $strRedirect = \Environment::get('request');

            // Redirect to the last page visited
            if ($this->redirectBack && $_SESSION['LAST_PAGE_VISITED'] != '') {
                $strRedirect = $_SESSION['LAST_PAGE_VISITED'];
            } else {
                // Redirect to the jumpTo page
                if ($this->jumpTo && ($objTarget = $this->objModel->getRelated('jumpTo')) !== null) {
                    $strRedirect = $this->generateFrontendUrl($objTarget->row());
                }

                // Overwrite the jumpTo page with an individual group setting
                $objMember = \MemberModel::findByUsername(strtolower(\Input::post('username')));

                if ($objMember !== null) {
                    \Input::setPost("username", strtolower(\Input::post('username')));
                    $arrGroups = deserialize($objMember->groups);

                    if (!empty($arrGroups) && is_array($arrGroups)) {
                        $objGroupPage = \MemberGroupModel::findFirstActiveWithJumpToByIds($arrGroups);

                        if ($objGroupPage !== null) {
                            $strRedirect = $this->generateFrontendUrl($objGroupPage->row());
                        }
                    }
                }
            }

            // Auto login is not allowed
            if (isset($_POST['autologin']) && !$this->autologin) {
                unset($_POST['autologin']);
                \Input::setPost('autologin', null);
            }

            // Login and redirect
            if ($this->User->login()) {

                $this->redirect($strRedirect);
            }

            $this->reload();
        }

        // Logout and redirect to the website root if the current page is protected
        if (\Input::post('FORM_SUBMIT') == 'tl_logout') {
            global $objPage;

            $this->import('FrontendUser', 'User');
            $strRedirect = \Environment::get('request');

            // Redirect to last page visited
            if ($this->redirectBack && strlen($_SESSION['LAST_PAGE_VISITED'])) {
                $strRedirect = $_SESSION['LAST_PAGE_VISITED'];
            } // Redirect home if the page is protected
            elseif ($objPage->protected) {
                $strRedirect = \Environment::get('base');
            }

            // Logout and redirect
            if ($this->User->logout()) {
                $this->redirect($strRedirect);
            }

            $this->reload();
        }

        return parent::generate();
    }

}