/**
 * GET /api/v1/tasks — employee-specific task allocations.
 */

export type EmployeeTaskSummary = {
  id: number;
  title: string;
  description: string | null;
  work_location: { id: number; name: string } | null;
  job_title: { id: number; name: string } | null;
  shift: {
    id: number;
    name: string;
    start_time: string | null;
    end_time: string | null;
  } | null;
  notes?: string | null;
  scheduled_date?: string | null;
  scheduled_date_display?: string | null;
  start_time?: string | null;
  end_time?: string | null;
  time_range?: string | null;
  completed?: boolean;
  completed_at?: string | null;
  completed_at_display?: string | null;
};

export type EmployeeTasksBody = {
  tasks: {
    date: string;
    work_location: { id: number; name: string } | null;
    tasks: EmployeeTaskSummary[];
    counts: {
      total: number;
    };
  };
};
