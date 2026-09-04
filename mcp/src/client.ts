import axios, { AxiosInstance, AxiosRequestConfig } from 'axios';

/**
 * WPForge REST API client.
 */
export class WPForgeClient {
    private client: AxiosInstance;
    private baseUrl: string;

    constructor(baseUrl: string, username: string, password: string) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.client = axios.create({
            baseURL: `${this.baseUrl}/wp-json/wpforge/v1`,
            auth: { username, password },
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            timeout: 30000,
        });

        this.client.interceptors.response.use(
            (response) => response,
            (error) => {
                if (error.response) {
                    const data = error.response.data;
                    throw new Error(data?.error?.message || data?.message || error.message);
                }
                throw error;
            }
        );
    }

    async get(endpoint: string, params?: Record<string, any>): Promise<any> {
        const config: AxiosRequestConfig = params ? { params } : {};
        const response = await this.client.get(endpoint, config);
        return response.data;
    }

    async post(endpoint: string, data?: any): Promise<any> {
        const response = await this.client.post(endpoint, data);
        return response.data;
    }

    async put(endpoint: string, data?: any): Promise<any> {
        const response = await this.client.put(endpoint, data);
        return response.data;
    }

    async delete(endpoint: string, params?: Record<string, any>): Promise<any> {
        const config: AxiosRequestConfig = params ? { params } : {};
        const response = await this.client.delete(endpoint, config);
        return response.data;
    }
}
