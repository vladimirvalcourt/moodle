# AI Course Generator Plugin

## Overview

This plugin enables instructors to generate complete courses using artificial intelligence. Simply provide a topic, target audience, and learning objectives, and the AI will create a structured course with lessons, assessments, and learning paths.

## Features

- 🚀 **AI-Powered Course Generation**: Create courses in minutes instead of weeks
- 🎯 **Smart Content**: Lessons tailored to your audience level
- 📝 **Auto-Assessments**: Generated quizzes aligned with Bloom's taxonomy
- ✅ **Quality Validation**: Automated content quality checks
- 💰 **Cost Control**: Rate limiting and token tracking
- 🔄 **Background Processing**: Async generation won't block your workflow

## Installation

1. Copy this directory to `/local/aicourse/` in your Moodle installation
2. Visit Site Administration → Notifications to install
3. Configure your OpenAI API key in plugin settings
4. Start generating courses!

## Configuration

### Required Settings

- **OpenAI API Key**: Your API key from https://platform.openai.com/api-keys
- **Daily Generation Limit**: Maximum courses per user per day (default: 10)

### Optional Settings

- **Claude API Key**: For Anthropic Claude support (not yet implemented)
- **Default Model**: GPT-4 Turbo recommended
- **Quality Threshold**: Minimum score for auto-approval (default: 75)
- **Enable Caching**: Reduce API costs by caching similar generations

## Usage

### Generating a Course

1. Navigate to **Site Administration → Local Plugins → AI Course Generator**
2. Fill in the course details:
   - Topic/title
   - Target audience level
   - Estimated duration
   - Learning objectives (optional)
3. Click "Generate Course"
4. Wait 1-2 minutes for generation to complete
5. Review the generated content
6. Edit as needed
7. Publish to create the actual Moodle course

### Managing Drafts

- View all your drafts on the main page
- Status indicators show progress (draft → review → published)
- Click "Review" to edit AI-generated content
- Published drafts link directly to the live course

## Permissions

Three capabilities are defined:

- `local/aicourse:generate` - Allow users to create AI courses (editing teachers, managers, course creators)
- `local/aicourse:review` - Allow users to review content (editing teachers, managers)
- `local/aicourse:manage` - Allow full management (managers only)

## Database Tables

The plugin creates five tables:

- `local_aicourse_drafts` - Course drafts before publication
- `local_aicourse_history` - Audit trail of generations
- `local_aicourse_paths` - Personalized learning paths
- `local_aicourse_assessments` - Generated quiz questions
- `local_aicourse_quality` - Content quality metrics

## Troubleshooting

### "API key not configured"
- Go to Site Administration → Local Plugins → AI Course Generator
- Enter your OpenAI API key
- Save changes

### "Rate limit reached"
- You've hit your daily generation limit
- Wait until tomorrow or ask an admin to increase your limit
- Check your usage stats on the main page

### "Generation failed"
- Check that your API key is valid and has credits
- Verify your internet connection
- Try simplifying your topic description
- Check Moodle error logs for details

## Development

### File Structure

```
local/aicourse/
├── classes/
│   ├── api/              # AI provider implementations
│   ├── generator/        # Course generation logic
│   ├── services/         # Business logic services
│   └── tasks/            # Background tasks
├── db/                   # Database schema
├── lang/en/              # Language strings
├── templates/            # UI templates (future)
├── amd/src/              # JavaScript modules (future)
├── index.php             # Main entry page
├── lib.php               # Core functions
├── settings.php          # Admin settings
└── version.php           # Plugin metadata
```

### Adding New AI Providers

1. Implement `\local_aicourse\api\ai_provider` interface
2. Add provider selection in `local_aicourse_get_provider()` function
3. Update settings.php to include new provider option

### Extending Functionality

- Add custom validation in `content_validator` service
- Modify prompts in the OpenAI client
- Extend the generator to support more content types
- Add React components for enhanced UI

## License

This plugin is part of Moodle and licensed under GPL-3.0-or-later.

## Support

For issues or feature requests, please contact the plugin maintainer or visit the Moodle community forums.

---

**Version**: 0.1.0  
**Requires**: Moodle 5.2+  
**PHP**: 8.3+
