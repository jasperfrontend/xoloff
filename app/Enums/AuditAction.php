<?php

namespace App\Enums;

/**
 * What an audit log entry records (SPEC §3).
 *
 * StatusChanged and NotificationSent are named by the spec but nothing writes
 * them yet: statuses arrive in M4 and notifications in M7. They are declared
 * here because the spec defines this as one log covering CRUD and events alike,
 * and splitting that definition across milestones would hide the design.
 */
enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    /**
     * Downloading a PDF. Not in the spec's list of actions, which stops at
     * notification_sent, but SPEC §6 requires PDF actions to be visible in the
     * audit log, and none of the listed values describes one.
     */
    case Exported = 'exported';

    case StatusChanged = 'status_changed';
    case NotificationSent = 'notification_sent';

    /**
     * How the action reads in the log. Kept here rather than in the page, so
     * the wording cannot drift between the filter and the rows it filters.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => __('Created'),
            self::Updated => __('Updated'),
            self::Deleted => __('Deleted'),
            self::Exported => __('Downloaded as PDF'),
            self::StatusChanged => __('Status changed'),
            self::NotificationSent => __('Notification sent'),
        };
    }
}
