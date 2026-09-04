import type { ActivityEntry, ActivityTemplate } from '../types/activity';

export const seedActivity: ActivityEntry[] = [
{
  id: 'seed-1',
  agent: 'Claude Code',
  method: 'POST',
  endpoint: '/wpforge/v1/posts',
  description: 'Updated "Pricing" page via Elementor',
  status: 200,
  statusText: 'OK',
  time: '2m ago'
},
{
  id: 'seed-2',
  agent: 'Claude Code',
  method: 'GET',
  endpoint: '/wpforge/v1/media',
  description: 'Listed media library assets',
  status: 200,
  statusText: 'OK',
  time: '6m ago'
},
{
  id: 'seed-3',
  agent: 'Claude Desktop',
  method: 'POST',
  endpoint: '/wpforge/v1/elementor',
  description: 'Rendered updated section layout',
  status: 200,
  statusText: 'OK',
  time: '9m ago'
},
{
  id: 'seed-4',
  agent: 'Claude Code',
  method: 'GET',
  endpoint: '/wpforge/v1/database',
  description: 'Queried post meta (read-only)',
  status: 200,
  statusText: 'OK',
  time: '14m ago'
},
{
  id: 'seed-5',
  agent: 'Scheduler',
  method: 'POST',
  endpoint: '/wpforge/v1/backup',
  description: 'Triggered scheduled backup',
  status: 200,
  statusText: 'OK',
  time: '22m ago'
},
{
  id: 'seed-6',
  agent: 'Qwen Agent',
  method: 'GET',
  endpoint: '/wpforge/v1/plugins',
  description: 'Checked active plugin versions',
  status: 200,
  statusText: 'OK',
  time: '41m ago'
},
{
  id: 'seed-7',
  agent: 'Claude Code',
  method: 'POST',
  endpoint: '/wpforge/v1/filesystem',
  description: 'Wrote theme stylesheet override',
  status: 200,
  statusText: 'OK',
  time: '1h ago'
},
{
  id: 'seed-8',
  agent: 'Claude Desktop',
  method: 'GET',
  endpoint: '/wpforge/v1/logs',
  description: 'Fetched recent audit trail',
  status: 200,
  statusText: 'OK',
  time: '2h ago'
},
{
  id: 'seed-9',
  agent: 'Claude Code',
  method: 'POST',
  endpoint: '/wpforge/v1/media',
  description: 'Uploaded optimized hero image',
  status: 201,
  statusText: 'Created',
  time: '4h ago'
},
{
  id: 'seed-10',
  agent: 'Qwen Agent',
  method: 'GET',
  endpoint: '/wpforge/v1/posts',
  description: 'Fetched draft post list',
  status: 200,
  statusText: 'OK',
  time: '6h ago'
},
{
  id: 'seed-11',
  agent: 'Claude Code',
  method: 'GET',
  endpoint: '/wpforge/v1/diagnostics',
  description: 'Ran site health diagnostics',
  status: 200,
  statusText: 'OK',
  time: '7h ago'
},
{
  id: 'seed-12',
  agent: 'Claude Desktop',
  method: 'PUT',
  endpoint: '/wpforge/v1/menus',
  description: 'Reordered primary navigation items',
  status: 200,
  statusText: 'OK',
  time: '9h ago'
}];


export const incomingActivity: ActivityTemplate[] = [
{
  agent: 'Claude Code',
  method: 'GET',
  endpoint: '/wpforge/v1/site',
  description: 'Inspected site configuration',
  status: 200,
  statusText: 'OK'
},
{
  agent: 'Claude Desktop',
  method: 'POST',
  endpoint: '/wpforge/v1/pages',
  description: 'Created "Case Studies" draft page',
  status: 201,
  statusText: 'Created'
},
{
  agent: 'Qwen Agent',
  method: 'GET',
  endpoint: '/wpforge/v1/taxonomies',
  description: 'Listed categories and tags',
  status: 200,
  statusText: 'OK'
},
{
  agent: 'Claude Code',
  method: 'POST',
  endpoint: '/wpforge/v1/elementor',
  description: 'Patched hero widget spacing',
  status: 200,
  statusText: 'OK'
},
{
  agent: 'Claude Code',
  method: 'GET',
  endpoint: '/wpforge/v1/filesystem',
  description: 'Read child theme functions.php',
  status: 200,
  statusText: 'OK'
},
{
  agent: 'Claude Desktop',
  method: 'POST',
  endpoint: '/wpforge/v1/filesystem',
  description: 'Blocked write outside allowed root',
  status: 403,
  statusText: 'Forbidden'
},
{
  agent: 'Scheduler',
  method: 'GET',
  endpoint: '/wpforge/v1/system',
  description: 'Polled PHP and MySQL versions',
  status: 200,
  statusText: 'OK'
},
{
  agent: 'Claude Code',
  method: 'DELETE',
  endpoint: '/wpforge/v1/media/482',
  description: 'Removed unused banner asset',
  status: 200,
  statusText: 'OK'
},
{
  agent: 'Qwen Agent',
  method: 'GET',
  endpoint: '/wpforge/v1/users',
  description: 'Verified editor capabilities',
  status: 200,
  statusText: 'OK'
},
{
  agent: 'Claude Code',
  method: 'PUT',
  endpoint: '/wpforge/v1/themes',
  description: 'Activated staging theme variant',
  status: 200,
  statusText: 'OK'
}];