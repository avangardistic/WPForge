export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'DELETE';

export interface ActivityEntry {
  id: string;
  agent: string;
  method: HttpMethod;
  endpoint: string;
  description: string;
  status: number;
  statusText: string;
  time: string;
}

export type ActivityTemplate = Omit<ActivityEntry, 'id' | 'time'>;