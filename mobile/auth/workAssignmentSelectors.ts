import type { CurrentEmployeeBody, WorkAssignment } from './types';

export type AssignmentTabCard = {
  title: string;
  value: string;
  subvalue?: string | null;
};

function safe(v: string | null | undefined, fallback = 'Not assigned'): string {
  return v && v.trim() !== '' ? v : fallback;
}

export function getEmployeeWorkAssignment(
  employee: CurrentEmployeeBody['employee'] | null | undefined,
): WorkAssignment | null {
  return employee?.work_assignment ?? null;
}

export function departmentTabCard(
  employee: CurrentEmployeeBody['employee'] | null | undefined,
): AssignmentTabCard {
  const assignment = getEmployeeWorkAssignment(employee);
  const dept = assignment?.department;
  return {
    title: 'Department',
    value: safe(dept?.name),
    subvalue: dept?.code ? `Code: ${dept.code}` : null,
  };
}

export function workLocationTabCard(
  employee: CurrentEmployeeBody['employee'] | null | undefined,
): AssignmentTabCard & { latitude: number | null; longitude: number | null } {
  const assignment = getEmployeeWorkAssignment(employee);
  const loc = assignment?.work_location;
  return {
    title: 'Work location',
    value: safe(loc?.name),
    subvalue: loc?.address ?? null,
    latitude: loc?.latitude ?? null,
    longitude: loc?.longitude ?? null,
  };
}

export function shiftTabCard(
  employee: CurrentEmployeeBody['employee'] | null | undefined,
): AssignmentTabCard {
  const assignment = getEmployeeWorkAssignment(employee);
  const shift = assignment?.shift;
  const dayLabelMap: Record<string, string> = {
    mon: 'Mon',
    tue: 'Tue',
    wed: 'Wed',
    thu: 'Thu',
    fri: 'Fri',
    sat: 'Sat',
    sun: 'Sun',
  };
  const hours =
    shift?.start_time && shift?.end_time
      ? `${shift.start_time} - ${shift.end_time}`
      : null;
  const dayText =
    shift?.days && shift.days.length > 0
      ? shift.days.map((d) => dayLabelMap[d] ?? d).join(', ')
      : null;

  return {
    title: 'Shift',
    value: safe(shift?.name),
    subvalue: [hours, dayText, shift?.breaks_summary].filter(Boolean).join(' | ') || null,
  };
}
