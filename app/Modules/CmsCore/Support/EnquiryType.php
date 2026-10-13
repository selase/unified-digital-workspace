<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Support;

/**
 * The kinds of enquiry a public page can collect by email.
 */
enum EnquiryType: string
{
    case Volunteer = 'volunteer';
    case Membership = 'membership';
    case Partnership = 'partnership';
    case Donation = 'donation';

    public function label(): string
    {
        return match ($this) {
            self::Volunteer => 'Volunteer',
            self::Membership => 'Membership',
            self::Partnership => 'Partnership',
            self::Donation => 'Donation',
        };
    }

    public function heading(): string
    {
        return match ($this) {
            self::Volunteer => 'Tell us how you would like to help',
            self::Membership => 'Apply for membership',
            self::Partnership => 'Start a conversation about partnering',
            self::Donation => 'Ask about giving',
        };
    }

    public function messageLabel(): string
    {
        return match ($this) {
            self::Volunteer => 'How would you like to help?',
            self::Membership => 'Tell us a little about yourself',
            self::Partnership => 'What kind of partnership do you have in mind?',
            self::Donation => 'How would you like to give?',
        };
    }

    public function submitLabel(): string
    {
        return match ($this) {
            self::Volunteer => 'Send my details',
            self::Membership => 'Send my application',
            self::Partnership => 'Send my message',
            self::Donation => 'Send my message',
        };
    }

    public function asksForOrganisation(): bool
    {
        return $this === self::Partnership;
    }
}
