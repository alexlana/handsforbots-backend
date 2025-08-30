# System Prompt Integration

## Overview

The Universal LLM Backend now automatically loads and applies a system prompt from the `system-prompt.txt` file. This feature allows you to define a consistent system prompt that will be applied to all LLM requests unless explicitly overridden.

## How It Works

1. **Automatic Loading**: The system prompt is loaded from `system-prompt.txt` when the backend starts
2. **Global Application**: The system prompt is automatically added to all requests unless a custom system prompt is provided in the request context
3. **Provider Support**: The system prompt is properly formatted for each supported LLM provider:
   - **OpenAI**: Added as a system message at the beginning of the messages array
   - **Anthropic**: Included in the prompt construction with proper formatting
   - **Google Gemini**: Added as system instruction
   - **Ollama**: Included in the prompt with "System:" prefix

## File Structure

```
php/
├── app/
│   ├── universal_llm_backend.php    # Main backend file
│   ├── system-prompt.txt            # System prompt content
│   ├── config.php                   # Configuration file
│   └── ...
├── test_system_prompt.php           # Test script
└── docs/
    └── SYSTEM_PROMPT_INTEGRATION.md # This documentation
```

## Usage

### Basic Usage

Simply place your system prompt content in the `system-prompt.txt` file. The backend will automatically load and apply it to all requests.

### Override System Prompt

If you want to use a different system prompt for a specific request, you can override it by including it in the request context:

```json
{
  "provider": "openai",
  "model": "gpt-3.5-turbo",
  "messages": [
    {
      "role": "user",
      "content": "Your question here"
    }
  ],
  "parameters": {
    "max_tokens": 500,
    "temperature": 0.7
  },
  "context": {
    "system_prompt": "Your custom system prompt here"
  }
}
```

### Testing

Use the provided test script to verify the system prompt integration:

```bash
php test_system_prompt.php
```

Make sure to:
1. Update the `$backendUrl` variable to point to your backend
2. Set a valid API key in the `$apiKey` variable
3. Ensure the `system-prompt.txt` file exists and contains your desired system prompt

## Logging

The system prompt integration includes comprehensive logging:

- `system_prompt_loaded`: Logged when the system prompt file is successfully loaded
- `system_prompt_file_not_found`: Logged when the system prompt file doesn't exist
- `system_prompt_file_read_error`: Logged when there's an error reading the file
- `global_system_prompt_applied`: Logged when the global system prompt is applied to a request

## Configuration

The system prompt file path is hardcoded to `__DIR__ . '/system-prompt.txt'`. If you need to change this, modify the `loadSystemPrompt()` function in `universal_llm_backend.php`.

## Security Considerations

- The system prompt file should be readable by the web server
- Consider file permissions to prevent unauthorized access
- The system prompt content is logged (length only, not the full content) for debugging purposes

## Example System Prompt

Here's an example of what your `system-prompt.txt` file might contain:

```
Você é um especialista em implantação de WordPress na Google Cloud Platform usando Cloud Run, Cloud SQL, Cloud Storage, Cloud CDN e Cloud Load Balancing, conforme descrito por Alex Lana em seu artigo.

Seu objetivo é ajudar o usuário a entender o artigo e guiá-lo para implantar o WordPress na Google Cloud Platform, então todas as respostas devem ser baseadas no artigo.

IMPORTANTE:
- Responda sempre em português
- Seja útil, fornecendo exemplos ou instruções passo a passo como encontrados no artigo
- Se o usuário pedir informações gerais, responda de forma completa
- Se o usuário pedir informações específicas, responda de forma específica
```

## Troubleshooting

### System Prompt Not Applied

1. Check if the `system-prompt.txt` file exists in the same directory as `universal_llm_backend.php`
2. Verify file permissions (should be readable by the web server)
3. Check the logs for any error messages related to system prompt loading

### Custom System Prompt Not Working

1. Ensure you're providing the system prompt in the `context.system_prompt` field
2. Check that the request format is correct
3. Verify that the provider supports system prompts

### File Not Found Errors

1. Confirm the file path is correct
2. Check file permissions
3. Ensure the file has the correct name (`system-prompt.txt`)

## API Reference

### Request Format

The system prompt is automatically applied unless overridden in the request context:

```json
{
  "provider": "string",
  "model": "string", 
  "messages": [
    {
      "role": "user|assistant|system",
      "content": "string"
    }
  ],
  "parameters": {
    "max_tokens": "number",
    "temperature": "number",
    "top_p": "number"
  },
  "context": {
    "system_prompt": "string (optional, overrides global system prompt)"
  }
}
```

### Response Format

The response format remains unchanged:

```json
{
  "response": "string",
  "metadata": {
    "provider": "string",
    "model": "string",
    "usage": "object"
  }
}
```
