# OpenAI Extensions Provider #

Specialized AI provider for additional features such as Text-to-Speech capabilities using the OpenAI API.
The image generation uses features compatible with gpt-image-1 and not dall-e-3.

This plugin creates a provider that extends Moodle's AI functionality by adding text-to-speech capabilities via OpenAI's API. 
It works in conjunction with the `local_aixtension` plugin which provides the base infrastructure for AI actions and the `convert_text_to_speech` action.

## Dependencies ##

This plugin requires:
- **aiprovider_openai**: The base OpenAI provider
- **local_aixtension**: Main plugin that provides the `convert_text_to_speech` action and infrastructure for AI extensions

The `local_aixtension` plugin acts as the central system that defines available AI actions, while this plugin provides the specific implementation for OpenAI text-to-speech functionality.

## Installing via uploaded ZIP file ##

1. Ensure that dependencies `aiprovider_openai` and `local_aixtension` are already installed
2. Log in to your Moodle site as an admin and go to _Site administration > Plugins > Install plugins_
3. Upload the ZIP file with the plugin code
4. Check the plugin validation report and finish the installation

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to:

    {your/moodle/dirroot}/ai/provider/openai_extension

Afterwards, log in to your Moodle site as an admin and go to _Site administration > Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## License ##

2025 Laurent David <laurent@call-learning.fr>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.