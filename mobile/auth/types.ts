/**
 * Aligns with Laravel POST /api/v1/register and POST /api/v1/login responses.
 * Copy this folder into your RN app (e.g. src/auth/).
 */

import type { TimeClockStatus } from './timeClockTypes';

export type TenantHeaders = {
  'X-Company-Slug': string;
  'Content-Type': 'application/json';
  Accept?: 'application/json';
};

export type RegisterAuthBlock = {
  authenticated: false;
  token_issued: false;
  requires_email_password_login_after_approval: boolean;
};

export type RegisterSuccessBody = {
  message: string;
  company: { slug: string; name: string };
  employee: {
    public_id: string;
    email: string;
    full_legal_name: string | null;
    first_name: string | null;
    last_name: string | null;
    employment_status: string | null;
  };
  auth: RegisterAuthBlock;
};

/** Mirrors Laravel `work_assignment` on login / GET /api/v1/me (nullable when nothing assigned). */
export type WorkAssignment = {
  effective_from: string | null;
  notes: string | null;
  department: { id: number; name: string; code: string | null } | null;
  work_location: {
    id: number;
    name: string;
    address: string | null;
    notes: string | null;
    latitude: number | null;
    longitude: number | null;
  } | null;
  shift: {
    id: number;
    name: string;
    days: string[] | null;
    start_time: string | null;
    end_time: string | null;
    breaks_summary: string | null;
    breaks?: Array<{ label: string; minutes: number; paid: boolean }> | null;
    unpaid_break_minutes?: number | null;
    notes: string | null;
  } | null;
  shifts?: Array<{
    id: number;
    name: string;
    days: string[] | null;
    start_time: string | null;
    end_time: string | null;
    breaks_summary: string | null;
    breaks?: Array<{ label: string; minutes: number; paid: boolean }> | null;
    unpaid_break_minutes: number;
    notes: string | null;
  }>;
};

export type LoginSuccessBody = {
  token: string;
  token_type: 'Bearer';
  auth: {
    authenticated: true;
    token_issued: true;
    via: 'email_password';
  };
  employee: {
    public_id: string;
    email: string;
    full_legal_name: string | null;
    first_name: string | null;
    last_name: string | null;
    employment_status: string | null;
    company_display_name: string | null;
    work_assignment: WorkAssignment | null;
  };
};

/** GET /api/v1/me — same employee shape as login `employee` plus phone. */
export type CurrentEmployeeBody = {
  employee: {
    public_id: string;
    email: string;
    full_legal_name: string | null;
    first_name: string | null;
    last_name: string | null;
    employment_status: string | null;
    company_display_name: string | null;
    phone: string | null;
    employee_code?: string | null;
    job_title?: string | null;
    department?: string | null;
    role?: {
      job_title: string | null;
      department: string | null;
      employee_code: string | null;
    };
    work_assignment: WorkAssignment | null;
    time_clock?: TimeClockStatus;
  };
};

export type LoginErrorBody = {
  message: string;
  code:
    | 'invalid_credentials'
    | 'pending_approval'
    | 'registration_declined'
    | 'account_inactive';
};
