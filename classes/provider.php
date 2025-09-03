<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
namespace aiprovider_openai_extension;

use local_aixtension\aiactions\convert_text_to_speech;

/**
 * Specific openAI provider for text-to-speech (TTS) capabilities.
 *
 * @package   aiprovider_openai_extension
 * @copyright   2025 Laurent David <laurent@call-learning.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \aiprovider_openai\provider {
    /** @var string The openAI API key. */
    private string $apikey;
    /** @var string The organisation ID that goes with the key. */
    private string $orgid;

    /**
     * Class constructor.
     */
    public function __construct() {
        parent::__construct();
        $this->apikey = get_config('aiprovider_openai', 'apikey');
        $this->orgid = get_config('aiprovider_openai', 'orgid');
    }

    /**
     * Check this provider has the minimal configuration to work.
     *
     * @return bool Return true if configured.
     */
    public function is_provider_configured(): bool {
        return !empty($this->apikey);
    }

    #[\Override]
    public function get_action_list(): array {
        return [
            convert_text_to_speech::class
        ];
    }

    /**
     * Get any action settings for this provider.
     *
     * @param string $action The action class name.
     * @param \admin_root $ADMIN The admin root object.
     * @param string $section The section name.
     * @param bool $hassiteconfig Whether the current user has moodle/site:config capability.
     * @return array An array of settings.
     */
    public function get_action_settings(
        string $action,
        \admin_root $ADMIN,
        string $section,
        bool $hassiteconfig
    ): array {
        $actionname = substr($action, (strrpos($action, '\\') + 1));
        $settings = [];
        if ($actionname === 'convert_text_to_speech') {
            // Add the model setting.
            $settings[] = new \admin_setting_configtext(
                "aiprovider_openai_extension/action_{$actionname}_model",
                new \lang_string("action:{$actionname}:model", 'aiprovider_openai_extension'),
                new \lang_string("action:{$actionname}:model_desc", 'aiprovider_openai_extension'),
                'gpt-4o-mini-tts',
                PARAM_TEXT,
            );
            // Add API endpoint.
            $settings[] = new \admin_setting_configtext(
                "aiprovider_openai_extension/action_{$actionname}_endpoint",
                new \lang_string("action:{$actionname}:endpoint", 'aiprovider_openai_extension'),
                '',
                'https://api.openai.com/v1/audio/speech',
                PARAM_URL,
            );
            $settings[] = new \admin_setting_configselect(
                "aiprovider_openai_extension/action_{$actionname}_voice",
                new \lang_string("action:{$actionname}:voice", 'aiprovider_openai_extension'),
                new \lang_string("action:{$actionname}:voice_desc", 'aiprovider_openai_extension'),
                'alloy',
                [
                    'alloy' => 'Alloy',
                    'ash' => 'Ash',
                    'ballad' => 'Ballad',
                    'coral' => 'Coral',
                    'echo' => 'Echo',
                    'fable' => 'Fable',
                    'nova' => 'Nova',
                    'onyx' => 'Onyx',
                    'sage' => 'Sage',
                    'shimmer' => 'Shimmer',
                ]
            );
            $settings[] = new \admin_setting_configselect(
                "aiprovider_openai_extension/action_{$actionname}_format",
                new \lang_string("action:{$actionname}:format", 'aiprovider_openai_extension'),
                new \lang_string("action:{$actionname}:format_desc", 'aiprovider_openai_extension'),
                'mp3',
                ['mp3' => 'mp3', 'wav' => 'wav', 'flac' => 'flac', 'ogg' => 'ogg']
            );
            // Add system instruction settings.
            $settings[] = new \admin_setting_configtextarea(
                "aiprovider_openai_extension/action_{$actionname}_systeminstruction",
                new \lang_string("action:{$actionname}:systeminstruction", 'aiprovider_openai_extension'),
                new \lang_string("action:{$actionname}:systeminstruction_desc", 'aiprovider_openai_extension'),
                $action::get_system_instruction(),
                PARAM_TEXT
            );

        }

        return $settings;
    }
}
