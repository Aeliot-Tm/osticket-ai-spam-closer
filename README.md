# AI Spam Closer Plugin for osTicket

This plugin for [osTicket](https://github.com/osTicket/osTicket) automatically detects and closes spam tickets using AI (OpenAI or compatible APIs) analysis of ticket subject, body, and attached files. It uses keyword matching as a fast first-pass filter and AI for deeper content analysis when needed.

## Features

- **Automatic processing** of new tickets on creation
- **Manual trigger** via button in ticket view
- **Content analysis**: subject, message body, attached files
- **File format support**:
  - Images (JPG, JPEG, PNG, GIF, WebP) - via AI Vision API
  - PDF - via pdftotext (if installed)
  - Word (.doc, .docx) - via antiword or unzip (if installed)
  - Plain text files
- **Dual detection method**: fast keyword matching + AI analysis
- **Confidence scoring** (0-100%) for spam detection
- **Multiple API providers**: OpenAI or any OpenAI-compatible API endpoint
- **Wide model selection**: GPT-5, GPT-4.1, GPT-4o, o-series reasoning models, and custom models
- **Fallback mode**: works with keywords only when AI is unavailable
- **Internal notes** with closure reason and spam indicators
- **No email notifications** sent to users when closing spam tickets
- **Debug logging** for troubleshooting

## Installation

1. Copy the `osticket-ai-spam-closer` folder to `/include/plugins/`
2. Navigate to admin panel: **Admin Panel → Manage → Plugins**
3. Find "AI Spam Closer" and click **Install**
4. After installation, click on the plugin to configure

## Configuration

### API Settings

1. **API Provider** - choose API provider type:
   - `Open AI` - use OpenAI API (default)
   - `Custom` - use custom OpenAI-compatible API endpoint

2. **API Key** - your API key (optional)
   - For OpenAI: get one at https://platform.openai.com/api-keys
   - For Custom: use your provider's API key
   - **Leave empty** to use keyword matching only (no AI analysis)

3. **API URL** - custom API endpoint URL (required for Custom provider)
   - Must be OpenAI-compatible endpoint
   - Example: `https://api.example.com/v1/chat/completions`
   - For OpenAI provider, this is set automatically to `https://api.openai.com/v1/chat/completions`

4. **Model Name** - AI model to use for analysis:
   - **GPT-5 series** (latest):
     - `gpt-5.2` - Latest, improved reasoning
     - `gpt-5.1` - Coding & agentic tasks
     - `gpt-5.1-codex` / `gpt-5.1-codex-mini` / `gpt-5.1-codex-max` - Optimized for code
     - `gpt-5-mini` - Fast, 400K context
     - `gpt-5-nano` - Fastest, cheapest
   - **Reasoning models** (o-series, think longer before responding):
     - `o3` - Most advanced reasoning
     - `o3-mini` - Cost-efficient reasoning
     - `o4-mini` - Latest compact reasoning
     - `o1` / `o1-mini` - Extended/compact reasoning
   - **GPT-4.1 series** (improved coding & long context):
     - `gpt-4.1` - Best for coding, 1M context
     - `gpt-4.1-mini` / `gpt-4.1-nano` - Balanced/fastest
   - **GPT-4o series** (multimodal, recommended for images):
     - `gpt-4o` - Multimodal, capable
     - `gpt-4o-mini` - Fast and affordable (recommended)
   - **Legacy models**:
     - `gpt-4-turbo` / `gpt-3.5-turbo`
   - For Custom provider: enter any model name manually
   - For OpenAI provider: select from dropdown

5. **API Timeout** - maximum wait time for API response (seconds)
   - Default: `30` seconds
   - Increase if you experience timeout errors

6. **Temperature** - controls response randomness (0.0-2.0)
   - Lower values = more deterministic responses
   - Higher values = more creative/random responses
   - Default: `0.3` (recommended for classification tasks)
   - Range: 0.0 to 2.0
   - **Note**: Lower temperature values (0.0-0.5) are recommended for spam detection to ensure consistent, deterministic results

### Spam Detection Settings

1. **Spam Keywords (Fallback)** - backup keywords for when AI is unavailable or as fast first-pass filter
   - Format: comma or semicolon separated
   - Example: `viagra, casino, lottery, winner, click here, buy now, limited offer, earn money fast, work from home, make money online, free money, get paid, amazing offer`
   - Case-insensitive search
   - Partial text matching
   - **Important**: Keywords are checked first (fast), AI analysis runs only if no keyword matches found

2. **Close Reason Text** - internal note text added when closing spam tickets
   - Default: `This ticket has been automatically closed as spam based on content analysis.`
   - This text appears in the internal note along with the detection reason

3. **Auto-close on ticket creation** - automatically analyze and close new tickets if spam is detected
   - When enabled, plugin processes tickets immediately on creation
   - When disabled, only manual trigger is available

### Processing Settings

1. **Max File Size (MB)** - maximum file size to process for text extraction
   - Default: `10` MB
   - Larger files are skipped and logged

2. **Enable Debug Logging** - log processing details and AI requests for debugging
   - When enabled, detailed information is written to system logs
   - Useful for troubleshooting detection issues

## Usage

### Automatic Processing

When "Auto-close on ticket creation" is enabled, the plugin automatically:
1. Analyzes each new ticket on creation
2. Extracts text from subject, body, and attached files
3. Checks for spam keywords first (fast)
4. If no keyword matches, uses AI for deeper analysis (if configured)
5. Closes ticket if spam is detected
6. Adds an internal note with closure reason and spam indicators

### Manual Trigger

A **"🚫 Check for Spam and Close"** option appears in the "More" dropdown menu in the ticket view:
1. Open a ticket
2. Click the **"More"** button (with three dots) in the ticket toolbar
3. Select **"🚫 Check for Spam and Close"** from the dropdown menu
4. The plugin will analyze the ticket and close it if spam is detected
5. Results are shown in the UI with debug information (if logging enabled)

## Logic

### Detection Process

The plugin uses a two-stage detection approach:

1. **Keyword Check (First Pass - Fast)**
   - Searches ticket content for configured spam keywords
   - Case-insensitive, partial matching
   - If matches found → **SPAM detected**, ticket closed immediately
   - No AI call needed, very fast

2. **AI Analysis (Second Pass - If No Keywords Matched)**
   - Only runs if API key and API URL are configured
   - Analyzes ticket content using AI
   - Considers:
     - Promotional/commercial content
     - Suspicious links or offers
     - Generic mass-mailing patterns
     - Requests for personal information or money
     - Typical spam keywords and phrases
     - Irrelevant or off-topic content
   - Returns:
     - Spam determination (true/false)
     - Confidence score (0-100%)
     - Reasoning explanation
     - List of spam indicators
   - If spam detected → ticket closed

3. **Fallback Mode**
   - If AI is not configured or fails
   - Relies on keyword check results
   - Works without AI (keyword-only mode)

### Content Extraction

The plugin collects text from:
- **Ticket subject** - full subject line
- **Message body** - all thread entries, HTML stripped
- **Attached images** (JPG, PNG, GIF, WebP):
  - Uses AI Vision API (requires model with vision support, e.g., GPT-4o)
  - OCR text extraction
- **PDF files**:
  - Uses `pdftotext` utility (if installed)
  - Extracts all text content
- **Word documents** (.doc, .docx):
  - Uses `antiword` or `catdoc` for .doc files
  - Uses `unzip` for .docx files (XML extraction)
- **Plain text files**:
  - Direct reading

Files that are too large, unsupported, or fail to process are logged but don't prevent analysis.

### Internal Notes

Each spam detection and closure operation is recorded as an internal note:
- **Poster**: `SYSTEM`
- **Title**: `Spam Detected - Auto Closed`
- **Content**: 
  - Close reason text (from config)
  - Detection reason (keyword matches or AI analysis result)
  - Confidence score (if AI was used)
  - Spam indicators (if AI was used)
- **No email notifications** are sent to users

## Requirements

### Required:
- osTicket 1.18+
- PHP 7.2+
- PHP CURL extension

### Optional (for AI analysis):
- API key (OpenAI or compatible provider)
- API URL (for custom providers)

### Optional (for file processing):
- `pdftotext` - for PDF text extraction
- `antiword` - for Word .doc files
- `catdoc` - fallback for Word .doc files
- `unzip` - for Word .docx files

**Note**: The plugin can work in keyword-only mode without AI or file processing utilities. However, for best results, configure AI and install file processing utilities.

## Logging

When "Debug Logging" is enabled, information is written to the system log:
- Details of each ticket processing
- Number of configured keywords
- Content preview and length
- Keyword match results
- AI API requests and responses
- Confidence scores and spam indicators
- File processing details
- Errors and exceptions

Log viewing: depends on server configuration (usually `/var/log/apache2/error.log` or `/var/log/nginx/error.log`)

## Examples

### Common Spam Keywords

```
viagra, casino, lottery, winner, click here, buy now, limited offer, earn money fast, work from home, make money online, free money, get paid, amazing offer, urgent action required, guaranteed income, no investment, risk-free, act now, limited time, exclusive offer
```

### Financial Spam

```
get rich quick, investment opportunity, cryptocurrency, bitcoin, forex trading, binary options, passive income, money making scheme, pyramid scheme, ponzi scheme
```

### Phishing Keywords

```
verify account, update payment, account suspended, security alert, click to confirm, urgent verification, account locked, password reset required
```

## Troubleshooting

### Plugin doesn't close spam tickets

1. Check that "Auto-close on ticket creation" is enabled (for automatic mode)
2. Verify spam keywords are configured
3. If using AI: ensure API key and API URL are correct
4. Enable Debug Logging and check logs
5. Check that tickets actually contain spam keywords or are detected by AI

### AI analysis not working

1. Verify API key is correct and has sufficient balance (for OpenAI)
2. Check API URL is correct (for Custom provider)
3. Ensure API Timeout is sufficient (increase if requests timeout)
4. Verify model name is correct and available
5. Check Temperature value is within range (0.0-2.0)
6. Check system logs for API errors
7. Test API connection manually

### Files are not processed

1. Check file sizes (don't exceed Max File Size setting)
2. For PDF/Word: install pdftotext/antiword/catdoc utilities
3. For images: verify model supports Vision API (gpt-4o, gpt-4o-mini, or compatible)
4. Check system logs for file processing errors

### False positives (legitimate tickets closed as spam)

1. Review spam keywords - remove or refine overly broad terms
2. Lower Temperature value (e.g., 0.1-0.2) for more deterministic, conservative detection
3. Review AI analysis results in debug logs
4. Consider disabling auto-close and using manual trigger only
5. Add exceptions to keywords list

### False negatives (spam tickets not detected)

1. Add more spam keywords to the list
2. Ensure AI is configured and working
3. Review detection results in debug logs
4. Check that file attachments are being processed
5. Verify keywords match spam patterns in your tickets

### Manual trigger button not visible

1. Ensure plugin is installed and enabled
2. Check that you have permission to view tickets
3. Verify plugin files are correctly placed in `/include/plugins/`
4. Clear browser cache and reload page

## Usage Costs

OpenAI API is paid, approximate prices (may vary):
- **GPT-4o-mini**: ~$0.15 per 1M input tokens (recommended for spam detection)
- **GPT-4o**: ~$5 per 1M input tokens
- **GPT-4.1 series**: varies by tier
- **o-series (reasoning)**: premium pricing
- **Vision API**: ~$0.01 per image

For Custom API providers, check your provider's pricing.

**Cost optimization tips**:
- Use keyword matching first (free, fast) - most spam will be caught here
- Use `gpt-4o-mini` for AI analysis - best balance of cost and quality
- Only process files when necessary (images, PDFs with text)
- Monitor API usage in debug logs

**Recommendation**: The plugin is designed to minimize API calls by checking keywords first. Most spam will be caught by keywords, and AI will only be used for edge cases.

## Technical Details

### Unique Identifiers
- Plugin ID: `osticket:ai-spam-closer`
- Classes: `AISpamCloser*`
- Ajax endpoint: `/ai-spam-closer/analyze`
- CSS classes: `.ai-spam-closer-*`

### API Client
- Class: `AISpamCloserAPIClient`
- Supports OpenAI and OpenAI-compatible endpoints
- Temperature: configurable (default: 0.3, range: 0.0-2.0)
- JSON mode: enabled for structured responses

### File Processing
- Images: base64 encoded, sent to Vision API
- PDF: requires `pdftotext` system utility
- Word .doc: requires `antiword` or `catdoc`
- Word .docx: uses `unzip` to extract XML, then parses text
- Text files: direct file reading

### Performance
- Keyword check: < 1ms (in-memory string search)
- AI analysis: 1-5 seconds (depends on API response time)
- File processing: varies by file size and type
- Overall: most tickets processed in < 1 second (keyword match), AI tickets in 2-6 seconds

## Authors

- Anatoly Melnikov

## Version

1.0.0

## License

This plugin is licensed under the GNU General Public License v2 (GPL-2.0).

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

See the [LICENSE](LICENSE) file for the full license text.
