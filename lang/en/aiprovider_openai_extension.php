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

/**
 * Specific openAI provider for text-to-speech (TTS) capabilities.
 *
 * @package     aiprovider_openai_extension
 * @copyright   2025 Laurent David <laurent@call-learning.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'OpenAI TTS';
$string['plugindesc'] = 'Minimal provider that reuses settings from the OpenAI provider and exposes a Text-to-Speech action.';

$string['action:convert_text_to_speech:endpoint'] = 'Endpoint';
$string['action:convert_text_to_speech:endpoint_desc'] = 'Endpoint for the OpenAI TTS API (e.g., https://api.openai.com/v1/audio/speech). This is usually not needed as it is set by the OpenAI provider.';
$string['action:convert_text_to_speech:model'] = 'TTS model';
$string['action:convert_text_to_speech:model_desc'] = 'Model used to synthesize speech from text (e.g., gpt-4o-mini-tts).';
$string['action:convert_text_to_speech:voice'] = 'Voice (choose from OpenAI voices)';
$string['action:convert_text_to_speech:voice_desc'] = 'Can be any OpenAI voice, such as "alloy", "adam", etc.';
$string['action:convert_text_to_speech:format'] = 'Audio response format';
$string['action:convert_text_to_speech:format_desc'] = 'Audio format for the response, such as "mp3", "wav", "flac", or "pcm".';
$string['action:convert_text_to_speech:systeminstruction'] = 'System instruction';
$string['action:convert_text_to_speech:systeminstruction_desc'] = 'System instruction to guide the TTS model. This can include specific instructions on how to read the text, such as "Read this text aloud in a clear and engaging manner."';
$string['field_input'] = 'Text to speak';
$string['field_voice'] = 'Voice to use';
$string['field_model'] = 'TTS model to use';
$string['field_instructions'] = 'Additional instructions for the TTS model';
$string['field_response_format'] = 'Response format';
