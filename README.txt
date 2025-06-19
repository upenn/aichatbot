CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended modules
 * Installation
 * Configuration
 * Maintainers

INTRODUCTION
------------

The AI Chatbot module provides a frontend chatbot block
and acts as an AI agent for your website. It interacts with site 
visitors using your custom content/data. Currently the module is 
integrated with Open AI. The AI prompt, context can be customized
so that the AI agent just talks about your website/company and nothing
else. Module supports configurable prompts, welcome messages, 
agent name and more.

REQUIREMENTS
------------

 * Open AI API Key.

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module.

FEATURES
--------
- Configurable OpenAI prompt and welcome message
- Configurable agent name
- AI works on your custom data context
- Mobile-friendly frontend chat UI (fixed bottom-right)
- Open/Close chat window with smooth scroll
- Session persistence and reset functionality
- Caching of responses for improved performance

CONFIGURATION
-------------
 * Configure the AI API Settings (/admin/config/aichatbot/api)
 * Configure the AI Prompt, Custom data (/admin/config/services/aichatbot-prompt)
 
FRONTEND BLOCK
--------------
The module provides a block titled "AI Chatbot".

- Go to: /admin/structure/block
- Place the block in a region (e.g., Header)
- The block appears fixed in the bottom-right of the screen

TECHNICAL NOTES
---------------
- Uses session to store chat history with 30-minute timeout
- Cache stores answers for 30 minutes using hashed question+prompt
- Controller uses custom data context before calling OpenAI service

PERMISSIONS
-----------
No special permissions required. Default access uses 'access content'.

MAINTAINERS
-----------
Current maintainer:
 * Gaurav Kumar - https://www.drupal.org/u/gauravkumar
