/**
 * WPForge JavaScript Client
 */
class WPForgeClient {
    constructor(baseUrl, username, password) {
        this.baseUrl = baseUrl.replace(/\/$/, '') + '/wp-json/wpforge/v1';
        this.auth = btoa(`${username}:${password}`);
    }

    async request(method, endpoint, body = null) {
        const options = {
            method,
            headers: {
                'Authorization': `Basic ${this.auth}`,
                'Content-Type': 'application/json',
            },
        };
        if (body) options.body = JSON.stringify(body);
        const response = await fetch(`${this.baseUrl}${endpoint}`, options);
        return response.json();
    }

    async get(endpoint, params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request('GET', query ? `${endpoint}?${query}` : endpoint);
    }

    async post(endpoint, data) { return this.request('POST', endpoint, data); }
    async put(endpoint, data) { return this.request('PUT', endpoint, data); }
    async delete(endpoint) { return this.request('DELETE', endpoint); }
}

// Usage
const client = new WPForgeClient('https://your-site.com', 'admin', 'your-app-password');
client.get('/status').then(r => console.log(r));
