<?php

namespace App\Support;

/**
 * Roles and what each may do. The single source for the seeder, gates and tests.
 * Officers conclude; roles only decide who may act on what.
 */
final class Roles
{
    public const RISK_ANALYST = 'risk_analyst';

    public const REFUND_AUDITOR = 'refund_auditor';

    public const SPECIAL_SECTORS_AUDITOR = 'special_sectors_auditor';

    public const TEAM_LEAD = 'team_lead';

    public const RULE_OWNER = 'rule_owner';

    public const DATA_TEAM = 'data_team';

    public const AUDIT_VIEWER = 'audit_viewer';

    public const SUPER_ADMIN = 'super_admin';

    public const PERMISSIONS = [
        'view_worklist',            // see cases routed to my team
        'view_company_records',     // open a company profile and its source rows
        'manage_cases',             // take, reassign and work a case
        'refer_cases',              // hand a case to another team
        'send_information_requests',
        'record_outcomes',          // record one of the four officer outcomes
        'second_signoff',           // countersign "explained, legitimate" at or above the threshold
        'configure_rules',          // edit pattern thresholds and wording
        'review_data_errors',       // work the data review queue (system errors)
        'view_audit_log',
        'use_chat',
        'manage_demo_clock',
        'receive_data',             // bring files in through Data intake
        'manage_users',
    ];

    private const CASEWORK = [
        'view_worklist', 'view_company_records', 'manage_cases', 'refer_cases',
        'send_information_requests', 'record_outcomes', 'use_chat',
    ];

    /** @return array<string, list<string>> role => permissions (super_admin gets everything via Gate::before) */
    public static function matrix(): array
    {
        return [
            self::RISK_ANALYST => self::CASEWORK,
            self::REFUND_AUDITOR => self::CASEWORK,
            self::SPECIAL_SECTORS_AUDITOR => self::CASEWORK,
            self::TEAM_LEAD => [...self::CASEWORK, 'second_signoff', 'view_audit_log'],
            self::RULE_OWNER => ['view_worklist', 'view_company_records', 'configure_rules', 'use_chat'],
            self::DATA_TEAM => ['view_worklist', 'view_company_records', 'review_data_errors', 'receive_data'],
            self::AUDIT_VIEWER => ['view_company_records', 'view_audit_log'],
            self::SUPER_ADMIN => self::PERMISSIONS,
        ];
    }

    public static function label(string $role): string
    {
        return match ($role) {
            self::RISK_ANALYST => 'Risk analyst',
            self::REFUND_AUDITOR => 'Refund auditor',
            self::SPECIAL_SECTORS_AUDITOR => 'Special Sectors auditor',
            self::TEAM_LEAD => 'Team lead',
            self::RULE_OWNER => 'Rule owner',
            self::DATA_TEAM => 'Data team',
            self::AUDIT_VIEWER => 'Internal audit',
            self::SUPER_ADMIN => 'Super admin',
            default => $role,
        };
    }
}
