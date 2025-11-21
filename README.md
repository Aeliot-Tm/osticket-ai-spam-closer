# AI Spam Closer Plugin for osTicket

Automatic spam detection and ticket closing using AI (OpenAI GPT) analysis of ticket subject, body, and attachments.

## Features

- ✅ **AI-powered spam analysis** using OpenAI GPT-4o/GPT-4o-mini
- ✅ Automatic spam checking for new tickets
- ✅ Spam detection with confidence score
- ✅ Text extraction from images (Vision API)
- ✅ Text extraction from attachments (PDF, DOC, DOCX, TXT, images)
- ✅ Fallback to keyword matching when AI is unavailable
- ✅ Automatic spam ticket closing
- ✅ Manual trigger button in "More" menu
- ✅ Internal notes with closure reason
- ✅ No email notifications sent to users

## Installation

1. Copy the plugin directory to `include/plugins/`
2. Go to Admin Panel → Manage → Plugins
3. Find "AI Spam Closer" and click "Add New Instance"
4. Configure keywords and settings
5. Enable the plugin

## Configuration

### OpenAI API Key (Optional)
API key from OpenAI. Get it at https://platform.openai.com/api-keys
Leave empty to use keyword matching only.

### OpenAI Model
Choose model for analysis:
- **GPT-4o** - most powerful, expensive
- **GPT-4o Mini** - fast and affordable (recommended)
- **GPT-4 Turbo** - balance of speed and quality
- **GPT-3.5 Turbo** - cheapest option

### API Timeout (seconds)
Maximum wait time for OpenAI API response.

### Spam Keywords (Fallback)
Backup keywords for when AI is unavailable:
```
viagra, casino, lottery, winner, click here, buy now, limited offer, earn money fast, work from home, make money online, free money, get paid, amazing offer
```

Supported separators:
- Comma (,)
- Semicolon (;)
- Case-insensitive search

### Close Reason Text
Internal note text added when closing spam tickets.

### Auto-close on ticket creation
Enables automatic checking and closing of new tickets on creation.

### Enable Debug Logging
Enables detailed logging for debugging.

### Max File Size (MB)
Maximum file size for text extraction from attachments.

## Usage

### Automatic Mode
When "Auto-close on ticket creation" is enabled, the plugin automatically checks all new tickets and closes those containing spam keywords or detected by AI.

### Manual Trigger
In any ticket card, go to **"More"** menu (⚙️) → **"🚫 Check for Spam and Close"**. Click to manually check the ticket for spam.

## Technical Details

### Unique Identifiers
- Plugin ID: `osticket:ai-spam-closer`
- Classes: `AISpamCloser*`
- Ajax endpoint: `/ai-spam-closer/analyze`
- CSS classes: `.ai-spam-closer-*`

### AI Analysis
Plugin uses OpenAI GPT for:
1. **Ticket content analysis** - determines if ticket is spam
2. **Text extraction from images** - Vision API for OCR
3. **Confidence scoring** - confidence score 0-100%
4. **Decision explanation** - reasoning and list of spam indicators

### Text Extraction from Files
- **Images (JPG, PNG, GIF, WebP)**: OpenAI Vision API
- **PDF**: uses `pdftotext`
- **DOC**: uses `antiword` or `catdoc`
- **DOCX**: uses `unzip` for XML extraction
- **TXT**: direct reading

### Fallback Mode
If AI is unavailable or error occurs:
- Uses keywords from settings
- Checks via simple case-insensitive search
- Logs reason for fallback switch

### Logging
Internal notes added via `$ticket->logNote()` without sending email notifications.

## Analysis Logic

1. **Keyword check first** (fast and reliable)
   - If matches found → SPAM, close immediately
2. **AI analysis** (if API key configured)
   - Deeper content analysis
   - Confidence scoring
   - Spam indicators identification
3. **Fallback mode** (if AI fails)
   - Relies on keyword results
   - Works without AI

## Debug Mode

Enable "Enable Debug Logging" in settings to see:
- Number of configured keywords
- Content preview
- Keyword matches
- AI analysis results
- Processing steps

Debug info appears in the UI when checking tickets manually.

## Developers

Pavel Bahdanau, Anatoly Melnikov

## Version

1.0.0

## License

See LICENSE file for details.
