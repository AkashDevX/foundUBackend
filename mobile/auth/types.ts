/**
 * Aligns with Laravel POST /api/v1/register and POST /api/v1/login responses.
 * Copy this folder into your RN app (e.g. src/auth/).
 */

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
  work_location: { id: number; name: string; address: string | null; notes: string | null } | null;
  shift: {
    id: number;
    name: string;
    start_time: string | null;
    end_time: string | null;
    breaks_summary: string | null;
    notes: string | null;
  } | null;
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
    work_assignment: WorkAssignment | null;
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
