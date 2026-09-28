# AI Integration Setup Guide

## Overview
This integration adds AI-powered reporting and Q&A capabilities to your Smart Clinic application using OpenAI's GPT models.

## Features
- **Database Reports**: Generate comprehensive reports about your clinic data
- **Natural Language Q&A**: Ask questions about your clinic and get AI answers
- **Multiple Report Types**: General, financial, operational, and summary reports
- **Real-time Data**: Uses current database statistics
- **Context-Aware**: AI understands medical clinic context

## Setup Instructions

### 1. Install OpenAI Package
```bash
composer require openai-php/client
```

### 2. Add Environment Variables
Add to your `.env` file:
```env
OPENAI_API_KEY=your_openai_api_key_here
```

### 3. Get OpenAI API Key
1. Go to [OpenAI Platform](https://platform.openai.com/)
2. Sign up or login
3. Navigate to API Keys section
4. Create a new API key
5. Copy the key and add it to your `.env` file

### 4. Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
```

## API Endpoints

### Get AI Capabilities
```http
GET /api/ai/capabilities
Authorization: Bearer {jwt_token}
```

### Get Available Report Types
```http
GET /api/ai/report-types
Authorization: Bearer {jwt_token}
```

### Generate AI Report
```http
POST /api/ai/generate-report
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
    "report_type": "general" // optional: general, financial, operational, summary
}
```

### Ask AI Question
```http
POST /api/ai/ask-question
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
    "question": "How many patients do we have this month?"
}
```

## Example Usage

### Generate General Report
```bash
curl -X POST http://your-domain.com/api/ai/generate-report \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"report_type": "financial"}'
```

### Ask Question
```bash
curl -X POST http://your-domain.com/api/ai/ask-question \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"question": "What is our total revenue this month?"}'
```

## Report Types

1. **General**: Comprehensive clinic report with insights and recommendations
2. **Financial**: Focus on revenue, payments, and financial health
3. **Operational**: Focus on patient flow and doctor workload
4. **Summary**: Brief executive summary report

## Question Examples

You can ask questions like:
- "How many patients do we have?"
- "What is our total revenue this month?"
- "Which doctor has the most cases?"
- "How many unpaid bills do we have?"
- "What are our recent patient trends?"
- "Show me today's activity summary"

## Data Security

- ✅ Only uses your clinic database data
- ✅ No external data sharing
- ✅ Questions and data are sent to OpenAI for processing
- ✅ OpenAI's privacy policy applies to AI processing

## Cost Considerations

- Uses OpenAI's GPT-3.5-turbo model
- Cost per API call based on token usage
- Typical report: ~500-1500 tokens
- Typical question: ~200-800 tokens
- Check OpenAI pricing for current rates

## Troubleshooting

### Common Issues

1. **"Invalid API Key"**
   - Verify your OpenAI API key in `.env`
   - Check if the key has sufficient credits

2. **"Failed to generate report"**
   - Check your internet connection
   - Verify OpenAI service status
   - Check Laravel logs for detailed errors

3. **"Empty response"**
   - Database might be empty
   - Check if database connection is working

### Debug Mode
Add to `.env` for debugging:
```env
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

## Files Created/Modified

- `app/Services/AIService.php` - Core AI logic
- `app/Http/Controllers/AIController.php` - API endpoints
- `config/services.php` - OpenAI configuration
- `routes/api.php` - AI routes

## Next Steps

1. Set up OpenAI API key
2. Test the endpoints
3. Integrate with your frontend
4. Add custom prompts for your specific needs
5. Monitor usage and costs

## Support

For issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify OpenAI API status
3. Check database connectivity
4. Review API key permissions
