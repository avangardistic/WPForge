import { WPForgeClient } from '../client.js';

interface Tool {
    name: string;
    description: string;
    inputSchema: any;
    handler: (args: any) => Promise<any>;
}

export function createTools(client: WPForgeClient): Tool[] {
    return [
        // System
        { name: 'wp_site_inspect', description: 'Get comprehensive site info including WordPress, themes, plugins, and Elementor status', inputSchema: { type: 'object', properties: {} }, handler: async () => client.get('/site') },
        { name: 'wp_site_health', description: 'Get site health status', inputSchema: { type: 'object', properties: {} }, handler: async () => client.get('/health') },
        { name: 'wp_get_environment', description: 'Get PHP, server, and database environment info', inputSchema: { type: 'object', properties: {} }, handler: async () => client.get('/environment') },
        { name: 'wp_get_diagnostics', description: 'Get comprehensive system diagnostics', inputSchema: { type: 'object', properties: {} }, handler: async () => client.get('/diagnostics') },
        { name: 'wp_get_capabilities', description: 'Get current user capabilities', inputSchema: { type: 'object', properties: {} }, handler: async () => client.get('/capabilities') },

        // Content - Posts
        { name: 'wp_list_posts', description: 'List posts with optional filtering', inputSchema: { type: 'object', properties: { per_page: { type: 'number' }, page: { type: 'number' }, status: { type: 'string' }, search: { type: 'string' } } }, handler: async (a: any) => client.get('/posts', a) },
        { name: 'wp_get_post', description: 'Get a specific post by ID', inputSchema: { type: 'object', properties: { id: { type: 'number' } }, required: ['id'] }, handler: async (a: any) => client.get(`/posts/${a.id}`) },
        { name: 'wp_create_post', description: 'Create a new post', inputSchema: { type: 'object', properties: { post_title: { type: 'string' }, post_content: { type: 'string' }, post_status: { type: 'string' } }, required: ['post_title'] }, handler: async (a: any) => client.post('/posts', a) },
        { name: 'wp_update_post', description: 'Update an existing post', inputSchema: { type: 'object', properties: { id: { type: 'number' }, post_title: { type: 'string' }, post_content: { type: 'string' } }, required: ['id'] }, handler: async (a: any) => { const { id, ...data } = a; return client.put(`/posts/${id}`, data); } },
        { name: 'wp_delete_post', description: 'Delete a post', inputSchema: { type: 'object', properties: { id: { type: 'number' }, force: { type: 'boolean' } }, required: ['id'] }, handler: async (a: any) => client.delete(`/posts/${a.id}`) },

        // Content - Pages
        { name: 'wp_list_pages', description: 'List pages', inputSchema: { type: 'object', properties: { per_page: { type: 'number' }, page: { type: 'number' }, status: { type: 'string' } } }, handler: async (a: any) => client.get('/pages', a) },
        { name: 'wp_get_page', description: 'Get a specific page by ID', inputSchema: { type: 'object', properties: { id: { type: 'number' } }, required: ['id'] }, handler: async (a: any) => client.get(`/pages/${a.id}`) },
        { name: 'wp_create_page', description: 'Create a new page', inputSchema: { type: 'object', properties: { post_title: { type: 'string' }, post_content: { type: 'string' }, post_status: { type: 'string' } }, required: ['post_title'] }, handler: async (a: any) => client.post('/pages', a) },

        // Media
        { name: 'wp_list_media', description: 'List media attachments', inputSchema: { type: 'object', properties: { per_page: { type: 'number' }, page: { type: 'number' }, type: { type: 'string' } } }, handler: async (a: any) => client.get('/media', a) },
        { name: 'wp_get_media', description: 'Get a media item by ID', inputSchema: { type: 'object', properties: { id: { type: 'number' } }, required: ['id'] }, handler: async (a: any) => client.get(`/media/${a.id}`) },

        // Elementor
        { name: 'wp_elementor_status', description: 'Get Elementor status and capabilities', inputSchema: { type: 'object', properties: {} }, handler: async () => client.get('/elementor/status') },
        { name: 'wp_elementor_list_documents', description: 'List Elementor documents', inputSchema: { type: 'object', properties: { per_page: { type: 'number' }, page: { type: 'number' }, search: { type: 'string' } } }, handler: async (a: any) => client.get('/elementor/documents', a) },
        { name: 'wp_elementor_get_document', description: 'Get a specific Elementor document', inputSchema: { type: 'object', properties: { id: { type: 'number' } }, required: ['id'] }, handler: async (a: any) => client.get(`/elementor/documents/${a.id}`) },
        { name: 'wp_elementor_update_document', description: 'Update an Elementor document', inputSchema: { type: 'object', properties: { id: { type: 'number' }, title: { type: 'string' }, elementor_data: { type: 'array' } }, required: ['id'] }, handler: async (a: any) => { const { id, ...data } = a; return client.put(`/elementor/documents/${id}`, data); } },
        { name: 'wp_elementor_list_templates', description: 'List Elementor templates', inputSchema: { type: 'object', properties: { per_page: { type: 'number' }, type: { type: 'string' } } }, handler: async (a: any) => client.get('/elementor/templates', a) },

        // Filesystem
        { name: 'wp_list_files', description: 'List files in a directory', inputSchema: { type: 'object', properties: { path: { type: 'string' } }, required: ['path'] }, handler: async (a: any) => client.get('/files/list', a) },
        { name: 'wp_read_file', description: 'Read a file', inputSchema: { type: 'object', properties: { path: { type: 'string' } }, required: ['path'] }, handler: async (a: any) => client.get('/files/read', a) },
        { name: 'wp_write_file', description: 'Write content to a file', inputSchema: { type: 'object', properties: { path: { type: 'string' }, content: { type: 'string' }, dry_run: { type: 'boolean' } }, required: ['path', 'content'] }, handler: async (a: any) => client.post('/files/write', a) },
        { name: 'wp_delete_file', description: 'Delete a file', inputSchema: { type: 'object', properties: { path: { type: 'string' } }, required: ['path'] }, handler: async (a: any) => client.delete('/files/delete', a) },

        // Database
        { name: 'wp_database_status', description: 'Get database status', inputSchema: { type: 'object', properties: {} }, handler: async () => client.get('/database/status') },
        { name: 'wp_database_tables', description: 'List database tables', inputSchema: { type: 'object', properties: {} }, handler: async () => client.get('/database/tables') },
        { name: 'wp_database_query', description: 'Execute a SELECT query', inputSchema: { type: 'object', properties: { sql: { type: 'string' }, params: { type: 'object' } }, required: ['sql'] }, handler: async (a: any) => client.post('/database/query', a) },

        // Backup
        { name: 'wp_create_backup', description: 'Create a database backup', inputSchema: { type: 'object', properties: { type: { type: 'string' } } }, handler: async (a: any) => client.post('/backup', a) },
        { name: 'wp_list_backups', description: 'List all backups', inputSchema: { type: 'object', properties: {} }, handler: async () => client.get('/backup') },
        { name: 'wp_delete_backup', description: 'Delete a backup', inputSchema: { type: 'object', properties: { id: { type: 'string' } }, required: ['id'] }, handler: async (a: any) => client.delete(`/backup/${a.id}`) },

        // Cache
        { name: 'wp_flush_cache', description: 'Flush all caches', inputSchema: { type: 'object', properties: {} }, handler: async () => client.post('/cache/flush') },

        // Logs
        { name: 'wp_get_logs', description: 'Get audit logs', inputSchema: { type: 'object', properties: { limit: { type: 'number' }, operation: { type: 'string' } } }, handler: async (a: any) => client.get('/logs', a) },

        // Users
        { name: 'wp_list_users', description: 'List users', inputSchema: { type: 'object', properties: { per_page: { type: 'number' }, page: { type: 'number' } } }, handler: async (a: any) => client.get('/users', a) },
        { name: 'wp_get_user', description: 'Get a user by ID', inputSchema: { type: 'object', properties: { id: { type: 'number' } }, required: ['id'] }, handler: async (a: any) => client.get(`/users/${a.id}`) },

        // Themes & Plugins
        { name: 'wp_list_themes', description: 'List installed themes', inputSchema: { type: 'object', properties: {} }, handler: async () => client.get('/themes') },
        { name: 'wp_list_plugins', description: 'List installed plugins', inputSchema: { type: 'object', properties: {} }, handler: async () => client.get('/plugins') },

        // Menus
        { name: 'wp_list_menus', description: 'List navigation menus', inputSchema: { type: 'object', properties: {} }, handler: async () => client.get('/menus') },
    ];
}
