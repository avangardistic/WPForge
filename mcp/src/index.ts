#!/usr/bin/env node

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';
import { WPForgeClient } from './client.js';
import { createTools } from './tools/index.js';

interface Config {
    baseUrl: string;
    username: string;
    password: string;
}

class WPForgeMCPServer {
    private server: Server;
    private client: WPForgeClient;

    constructor(config: Config) {
        this.server = new Server(
            { name: 'wpforge-mcp-server', version: '1.0.0' },
            { capabilities: { tools: {} } }
        );
        this.client = new WPForgeClient(config.baseUrl, config.username, config.password);
        this.setupHandlers();
    }

    private setupHandlers(): void {
        const tools = createTools(this.client);

        this.server.setRequestHandler(ListToolsRequestSchema, async () => ({
            tools: tools.map(tool => ({
                name: tool.name,
                description: tool.description,
                inputSchema: tool.inputSchema,
            })),
        }));

        this.server.setRequestHandler(CallToolRequestSchema, async (request) => {
            const tool = tools.find(t => t.name === request.params.name);
            if (!tool) throw new Error(`Unknown tool: ${request.params.name}`);

            try {
                const result = await tool.handler(request.params.arguments || {});
                return {
                    content: [{ type: 'text' as const, text: JSON.stringify(result, null, 2) }],
                };
            } catch (error: any) {
                return {
                    content: [{ type: 'text' as const, text: JSON.stringify({ error: true, message: error.message }, null, 2) }],
                    isError: true,
                };
            }
        });

        this.server.onerror = (error) => console.error('[MCP Error]', error);
        process.on('SIGINT', async () => { await this.server.close(); process.exit(0); });
    }

    async start(): Promise<void> {
        const transport = new StdioServerTransport();
        await this.server.connect(transport);
        console.error('WPForge MCP Server running on stdio');
    }
}

const args = process.argv.slice(2);
const getArg = (name: string) => args.find(a => a.startsWith(`--${name}=`))?.split('=')[1];

const config: Config = {
    baseUrl: getArg('base-url') || process.env.WPFORGE_BASE_URL || '',
    username: getArg('username') || process.env.WPFORGE_USERNAME || '',
    password: getArg('password') || process.env.WPFORGE_PASSWORD || '',
};

if (!config.baseUrl || !config.username || !config.password) {
    console.error('Error: --base-url, --username, and --password are required');
    process.exit(1);
}

new WPForgeMCPServer(config).start().catch(console.error);
