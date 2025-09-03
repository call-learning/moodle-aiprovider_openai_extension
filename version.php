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

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'aiprovider_openai_extension';
$plugin->version   = 2025081000;
$plugin->requires  = 2024042200; // Requires Moodle 4.5 or later.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';

// This plugin requires the OpenAI provider to function.
$plugin->dependencies = [
    'aiprovider_openai' => ANY_VERSION,
    'local_aixtension' => ANY_VERSION, // This provides the action 'convert_text_to_speech'.
];
