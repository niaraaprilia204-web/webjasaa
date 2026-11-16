<?php

/**
 * TKJ Service Hub - Theme & Branding Configuration
 * All visual colors, school identity, and branding elements can be adjusted from this file.
 */
class Theme
{
    // Branding Identity
    public const APP_NAME = 'TKJ Service Hub';
    public const SCHOOL_NAME = 'SMK Negeri TKJ Center';
    public const TAGLINE = 'Layanan Teknis & Solusi IT Unit Bisnis Siswa TKJ';

    // Theme Palette (Bootstrap & CSS Custom Properties)
    public const PRIMARY_COLOR = '#2563eb';    // Royal Blue
    public const SECONDARY_COLOR = '#475569';  // Slate Gray
    public const ACCENT_COLOR = '#0ea5e9';     // Sky Blue
    public const DARK_COLOR = '#0f172a';       // Deep Slate
    public const SUCCESS_COLOR = '#10b981';    // Emerald Green
    public const WARNING_COLOR = '#f59e0b';    // Amber

    // Icons & Logos
    public const BRAND_ICON = 'bi-cpu';
    public const BRAND_LOGO_TEXT = 'TKJ <span class="text-primary fw-extrabold">Service Hub</span>';

    // Contact & Footer Details
    public const CONTACT_EMAIL = 'unitbisnis@tkjhub.sch.id';
    public const CONTACT_PHONE = '+62 812-3456-7890';
    public const ADDRESS = 'Gedung Unit Bisnis TKJ, Lab Komputer Utama';

    public static function getCssVariables()
    {
        return "
            :root {
                --theme-primary: " . self::PRIMARY_COLOR . ";
                --theme-secondary: " . self::SECONDARY_COLOR . ";
                --theme-accent: " . self::ACCENT_COLOR . ";
                --theme-dark: " . self::DARK_COLOR . ";
                --theme-success: " . self::SUCCESS_COLOR . ";
                --theme-warning: " . self::WARNING_COLOR . ";
            }
        ";
    }
}
