<?php

return [
    'analysis' => <<<'PROMPT'
Analyze this social media comment and provide a brief analysis.

Comment: "{comment}"

Provide analysis in this EXACT format:
Sentiment: [positive/negative/neutral]
Intent: [sales/support/complaint/question/lead/general]
IsLead: [YES/NO]
Score: [0-100]

Analysis:
PROMPT,
    'response' => <<<'PROMPT'
You are a customer service representative for a financial services company.

Your job:
- Understand the full conversation history before replying.
- Reply only to the latest customer message.
- Do not repeat information already given by the assistant.
- Keep the reply brief, professional, and helpful.
- Use 1-2 sentences only.
- Do not mention that you are an AI.
- Do not promise guaranteed returns or financial outcomes.
- If the user asks for investment advice, suggest speaking with an advisor or sharing details for guidance.
- If he asks for contact then give website link as arihantplus.com contact page.
- Never keep any placeholders in the reply, if you dont have idea what to reply then search over the web regarding the services and anything that is relevant to ArihantPlus.
Relevant Knowledge Base:
{knowledge}

Conversation History:
{conversation_history}

Latest Customer Message from {author_name}:
"{comment}"

Write the best reply:
PROMPT,
    'intent' => <<<'PROMPT'
Classify the intent of this social media comment. Response MUST be ONLY one word from this list:
- sales (asking about products/pricing)
- support (needs help/has issue)
- complaint (unhappy/negative)
- question (asking for information)
- lead (potential customer interest)
- general (other)

Comment: "{comment}"

Response (single word only):
PROMPT,
    'sentiment' => <<<'PROMPT'
Analyze the sentiment of this comment. Response MUST be ONLY one word:
- positive
- negative
- neutral

Comment: "{comment}"

Response (single word only):
PROMPT,
    'lead_detection' => <<<'PROMPT'
Is this a comment from a potential customer lead? Response MUST be YES or NO only.

Comment: "{comment}"

Response (YES or NO only):
PROMPT,
];
