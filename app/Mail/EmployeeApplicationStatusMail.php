<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

class EmployeeApplicationStatusMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public const RECEIVED = 'received';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    public function __construct(
        public Employee $employee,
        public Company $company,
        public string $status,
    ) {
        if (! in_array($status, [self::RECEIVED, self::APPROVED, self::DECLINED], true)) {
            throw new InvalidArgumentException('Unknown application email status.');
        }
    }

    public function envelope(): Envelope
    {
        $companyName = $this->company->name ?: 'your organisation';

        $subject = match ($this->status) {
            self::RECEIVED => 'We have received your application — '.$companyName,
            self::APPROVED => 'Your application has been approved — '.$companyName,
            self::DECLINED => 'An update on your application — '.$companyName,
        };

        return new Envelope(
            from: new Address(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
            ),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-status',
            with: [
                'headline' => $this->headline(),
                'statusLabel' => $this->statusLabel(),
                'accent' => $this->accent(),
                'accentSoft' => $this->accentSoft(),
                'paragraphs' => $this->paragraphs(),
                'nextSteps' => $this->nextSteps(),
            ],
        );
    }

    public function headline(): string
    {
        return match ($this->status) {
            self::RECEIVED => 'Application received',
            self::APPROVED => 'Application approved',
            self::DECLINED => 'Application update',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::RECEIVED => 'Under review',
            self::APPROVED => 'Approved',
            self::DECLINED => 'Not approved',
        };
    }

    public function accent(): string
    {
        return match ($this->status) {
            self::APPROVED => '#0B6E4F',
            self::DECLINED => '#374151',
            default => '#003D7A',
        };
    }

    public function accentSoft(): string
    {
        return match ($this->status) {
            self::APPROVED => '#F0F7F4',
            self::DECLINED => '#F4F5F7',
            default => '#F3F6FB',
        };
    }

    /**
     * @return list<string>
     */
    public function paragraphs(): array
    {
        $companyName = $this->company->name ?: 'the organisation';
        $appName = (string) config('app.name', 'CruLynk');

        return match ($this->status) {
            self::RECEIVED => [
                'Thank you for applying to '.$companyName.' through '.$appName.'.',
                'We have received your application, and it is now under review. An administrator at '.$companyName.' will assess the information you submitted.',
                'There is nothing further you need to do at this stage. We will email you when a decision has been made. You will be able to sign in to the '.$appName.' app only after your application is approved.',
            ],
            self::APPROVED => [
                'We are pleased to confirm that '.$companyName.' has approved your application.',
                'Your account is now active. You can sign in to the '.$appName.' app with the email address and password you created during registration.',
                'If you need help getting started, please contact '.$companyName.' directly.',
            ],
            self::DECLINED => [
                'Thank you for your interest in '.$companyName.' and for completing your application through '.$appName.'.',
                'After careful review, '.$companyName.' is unable to approve your application at this time. You will not be able to sign in to the '.$appName.' app for this organisation.',
                'This outcome relates only to '.$companyName.'. Any applications you submitted to other organisations will continue to be reviewed separately.',
                'We appreciate the time you invested in your application.',
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function nextSteps(): array
    {
        if ($this->status !== self::APPROVED) {
            return [];
        }

        $appName = (string) config('app.name', 'CruLynk');

        return [
            'Open the '.$appName.' app on your phone.',
            'Sign in with the email address and password from your registration.',
            'Complete your mandatory induction process in the "Training" section and then your organisation can assign your shifts and workplace details.',
        ];
    }
}
