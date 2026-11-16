<?php

/**
 * Base Controller Class
 * Extends core controller, automatically providing Theme options & layout wrappers.
 */
class BaseController extends Controller
{
    protected $theme;

    public function __construct()
    {
        $this->theme = [
            'appName'     => Theme::APP_NAME,
            'schoolName'  => Theme::SCHOOL_NAME,
            'tagline'     => Theme::TAGLINE,
            'brandIcon'   => Theme::BRAND_ICON,
            'brandLogo'   => Theme::BRAND_LOGO_TEXT,
            'cssVars'     => Theme::getCssVariables(),
            'email'       => Theme::CONTACT_EMAIL,
            'phone'       => Theme::CONTACT_PHONE,
            'address'     => Theme::ADDRESS
        ];
    }

    /**
     * Render full layout with header, navbar, sidebar, main content, and footer
     */
    protected function renderLayout($view, $data = [])
    {
        $data['theme'] = $this->theme;

        if (isset($_SESSION['user']['id'])) {
            require_once __DIR__ . '/../Models/Notification.php';
            try {
                $notificationModel = new Notification();
                $data['notifications'] = $notificationModel->forUser((int) $_SESSION['user']['id'], 8);
                $data['unreadNotificationCount'] = $notificationModel->unreadCount((int) $_SESSION['user']['id']);
            } catch (\Throwable $e) {
                $data['notifications'] = [];
                $data['unreadNotificationCount'] = 0;
            }
        } else {
            $data['notifications'] = [];
            $data['unreadNotificationCount'] = 0;
        }

        $this->view('layouts/main', array_merge($data, [
            'contentView' => $view
        ]));
    }
}
