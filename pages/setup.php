<?php

use rex_2fa\one_time_password;
use rex_2fa\one_time_password_config;

/** @var rex_addon $this */

$fragment = new rex_fragment();
$message = '';
$title = $this->i18n('2fa_setup');
$buttons = '';
$content = '';
$uri = '';
$success = false;

$csrfToken = rex_csrf_token::factory('2factor_auth_setup');
$func = rex_request('func', 'string');

if ('setup-email' === $func || 'setup-totp' === $func) {
    switch ($func) {
        case 'setup-email':
            $otpMethod = new \rex_2fa\method_email();
            break;
        default:
            $otpMethod = new \rex_2fa\method_totp();
            break;
    }

    $config = one_time_password_config::loadFromDb($otpMethod, rex::requireUser());
    $config->updateMethod($otpMethod);
    $user_id = rex::requireUser()->getId();
    rex_user::clearInstance($user_id);
    rex::setProperty('user', rex_user::get($user_id));
}

$otp = one_time_password::getInstance();
$otpMethod = $otp->getMethod();

if ('' !== $func && !$csrfToken->isValid()) {
    $message = '<div class="alert alert-danger">' . $this->i18n('csrf_token_invalid') . '</div>';
    $func = '';
}

if ('disable' === $func) {
    $config = one_time_password_config::loadFromDb($otpMethod, rex::requireUser());
    $config->disable();
    $func = '';
}

if (one_time_password::ENFORCED_ALL === $otp->isEnforced()) {
    $message .= '<div class="alert alert-warning">' . $this->i18n('2fa_enforced') . ': ' . $this->i18n('2fa_enforced_yes_all') . '</div>';
}
if (one_time_password::ENFORCED_ADMINS === $otp->isEnforced()) {
    $message .= '<div class="alert alert-warning">' . $this->i18n('2fa_enforced') . ': ' . $this->i18n('2fa_enforced_yes_admins') . '</div>';
}

$config = one_time_password_config::loadFromDb($otpMethod, rex::requireUser());
if ($otp->isEnabled() && $config->enabled) {
    $title = $this->i18n('status');
    switch ($config->method) {
        case 'email':
            $content = '<p>'.$this->i18n('2fa_status_email_info').'</p>';
            break;
        default:
            $content = '<p>'.$this->i18n('2fa_status_otp_info').'</p>';
            break;
    }
    $this->i18n('2fa_status_otp_instruction');
    $content .= '<p><a class="btn btn-delete" href="' . rex_url::currentBackendPage(['func' => 'disable'] + $csrfToken->getUrlParams()) . '">' . $this->i18n('2fa_disable') . '</a></p>';
} else {
    if ('' === $func) {
        $content .= '<p>'.$this->i18n('2factor_auth_2fa_page_totp_instruction').'</p>';
        $content .= '<p><a class="btn btn-setup" href="' . rex_url::currentBackendPage(['func' => 'setup-totp'] + $csrfToken->getUrlParams()) . '">' . $this->i18n('2fa_setup_start_totp') . '</a></p>';
        $content .= '<p>'.$this->i18n('2factor_auth_2fa_page_email_instruction').'</p>';
        $content .= '<p><a class="btn btn-setup" href="' . rex_url::currentBackendPage(['func' => 'setup-email'] + $csrfToken->getUrlParams()) . '">' . $this->i18n('2fa_setup_start_email') . '</a></p>';

    } elseif ('setup-totp' === $func) {
        // nothing todo
    } elseif ('setup-email' === $func) {
        if (!rex_addon::get('phpmailer')->isAvailable()) {
            $content = rex_view::error($this->i18n('2fa_setup_start_phpmailer_required'));
            $func = '';
        }

        $email = trim(rex::requireUser()->getEmail());
        if ('' !== $func && ('' === $email || false === strpos($email, '@'))) {
            $content = rex_view::error($this->i18n('2fa_setup_start_email_required'));
            $buttons = '<a class="btn btn-setup" href="' . rex_url::backendPage('profile') . '">' . $this->i18n('2fa_setup_start_email_open_profile') . '</a>';

            $func = '';
        }

        if ('' !== $func) {
            $message = rex_view::info($this->i18n('2fa_setup_start_email_send'));
            one_time_password::getInstance()->challenge();
        }
    } elseif ('verify-totp' === $func || 'verify-email' === $func) {
        $otp = rex_post('rex_login_otp', 'string', '');

        if ('' !== $otp) {
            if (one_time_password::getInstance()->verify($otp)) {
                $message = '<div class="alert alert-success">' . $this->i18n('2fa_setup_successfull') . '</div>';
                $config = one_time_password_config::loadFromDb($otpMethod, rex::requireUser());
                $config->enable();
                $content = '';
                $success = true;
            }
        }

        if (!$success) {
            $message = '<div class="alert alert-warning">' . $this->i18n('2fa_wrong_opt') . '</div>';
        }
    } else {
        throw new rex_exception('unknown state');
    }
}

if ('setup-email' === $func || 'verify-email' === $func || 'setup-totp' === $func || 'verify-totp' === $func) {
    $config = one_time_password_config::loadFromDb($otpMethod, rex::requireUser());
    $uri = $config->provisioningUri;

    $fragment->setVar('addon', $this, false);
    $fragment->setVar('csrfToken', $csrfToken, false);
    $fragment->setVar('message', $message, false);
    $fragment->setVar('buttons', $buttons, false);
    $fragment->setVar('uri', $uri, false);
    $fragment->setVar('success', $success, false);

    if ('setup-totp' === $func || 'verify-totp' === $func) {
        echo $fragment->parse('2fa.setup-totp.php');
    } else {
        echo $fragment->parse('2fa.setup-email.php');
    }
} else {
    $fragment = new rex_fragment();
    $fragment->setVar('before', $message, false);
    $fragment->setVar('heading', $title, false);
    $fragment->setVar('body', $content, false);
    $fragment->setVar('buttons', $buttons, false);
    echo $fragment->parse('core/page/section.php');
}
