<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../Models/Setting.php';

/**
 * Non-secret AI configuration managed by operators.
 *
 * The Gemini API key is read from .env / Config and is never stored in the
 * database or rendered in the UI.
 */
class AiSettingsService extends BaseService
{
    private $settingModel;

    public function __construct()
    {
        $this->settingModel = new Setting();
    }

    public function getSettings()
    {
        $stored = $this->settingModel->getAllKeyed();

        $defaultModel = defined('AI_MODEL_NAME') ? AI_MODEL_NAME : 'gemini-1.5-flash';
        $enabled = isset($stored['ai_api_enabled'])
            ? filter_var($stored['ai_api_enabled'], FILTER_VALIDATE_BOOLEAN)
            : true;

        $temperature = isset($stored['ai_temperature']) ? (float) $stored['ai_temperature'] : 0.4;
        if ($temperature < 0 || $temperature > 1) {
            $temperature = 0.4;
        }

        $model = trim((string) ($stored['ai_model'] ?? ''));
        if ($model === '' || !$this->isValidModel($model)) {
            $model = $defaultModel;
        }

        return [
            'api_enabled'      => $enabled,
            'model'            => $model,
            'temperature'      => $temperature,
            'extra_instruction'=> trim((string) ($stored['ai_extra_instruction'] ?? '')),
        ];
    }

    public function save($data)
    {
        $model = trim((string) ($data['model'] ?? ''));
        $temperatureRaw = trim((string) ($data['temperature'] ?? '0.4'));
        $extraInstruction = trim((string) ($data['extra_instruction'] ?? ''));
        $apiEnabled = !empty($data['api_enabled']);

        if (!$this->isValidModel($model)) {
            return $this->response(false, 'Nama model AI tidak valid. Gunakan huruf, angka, titik, garis bawah, atau tanda hubung.');
        }

        if (!is_numeric($temperatureRaw)) {
            return $this->response(false, 'Temperature harus berupa angka antara 0 dan 1.');
        }

        $temperature = (float) $temperatureRaw;
        if ($temperature < 0 || $temperature > 1) {
            return $this->response(false, 'Temperature harus berada di antara 0 dan 1.');
        }

        if (mb_strlen($extraInstruction) > 1000) {
            return $this->response(false, 'Instruksi tambahan maksimal 1000 karakter.');
        }

        $saved = $this->settingModel->set('ai_model', $model)
            && $this->settingModel->set('ai_temperature', (string) $temperature)
            && $this->settingModel->set('ai_extra_instruction', $extraInstruction)
            && $this->settingModel->set('ai_api_enabled', $apiEnabled ? '1' : '0');

        if (!$saved) {
            return $this->response(false, 'Konfigurasi AI gagal disimpan. Pastikan migrasi database sudah dijalankan.');
        }

        return $this->response(true, 'Konfigurasi AI berhasil diperbarui.');
    }

    public function isApiKeyConfigured()
    {
        return defined('GEMINI_API_KEY') && trim((string) GEMINI_API_KEY) !== '';
    }

    public function getModelOptions()
    {
        return [
            'gemini-1.5-flash',
            'gemini-1.5-pro',
            'gemini-2.0-flash',
            'gemini-2.5-flash',
        ];
    }

    private function isValidModel($model)
    {
        return is_string($model) && preg_match('/^[A-Za-z0-9._-]{3,80}$/', $model) === 1;
    }
}
