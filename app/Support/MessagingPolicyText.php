<?php

namespace App\Support;

/**
 * Canonical workplace messaging Terms of Use (Google Play UGC-oriented).
 * Kept concise for in-app acceptance while covering required UGC points.
 */
final class MessagingPolicyText
{
    public static function defaultContent(): string
    {
        return implode("\n\n", [
            "1. Scope\n\nThese Messaging Terms of Use cover CruLynk workplace chat (messages and attachments). They are separate from the Privacy Policy. You must accept them before sending content.",
            "2. Workplace use only\n\nMessaging is for active employees and organisation admins only. It is a private work channel—not a public social, anonymous, or dating service—and is not directed at children.",
            "3. Prohibited content\n\nDo not send harassment, bullying, threats, hate speech, sexually explicit material, CSAM/child exploitation (zero tolerance), violence, illegal activity, spam, fraud, phishing, impersonation, doxxing, or unauthorised sharing of confidential data. This applies to text and attachments.",
            "4. Report, block, and enforcement\n\nUse in-app report tools for violations; you may also block users in one-to-one chats. Organisation admins review reports and may remove content or restrict messaging. CSAM or content that endangers children will be removed and may be reported to authorities as required by law.",
            "5. Acceptance\n\nBy accepting, you agree to these terms. Messaging stays locked until you accept. Updated terms may require re-acceptance before you can send messages again.",
        ]);
    }
}
