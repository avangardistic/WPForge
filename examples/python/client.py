"""
WPForge Python Client
"""
import requests

class WPForgeClient:
    def __init__(self, base_url: str, username: str, password: str):
        self.base_url = f"{base_url.rstrip('/')}/wp-json/wpforge/v1"
        self.auth = (username, password)

    def get(self, endpoint: str, params: dict = None) -> dict:
        return requests.get(f"{self.base_url}{endpoint}", auth=self.auth, params=params).json()

    def post(self, endpoint: str, data: dict = None) -> dict:
        return requests.post(f"{self.base_url}{endpoint}", auth=self.auth, json=data).json()

    def put(self, endpoint: str, data: dict = None) -> dict:
        return requests.put(f"{self.base_url}{endpoint}", auth=self.auth, json=data).json()

    def delete(self, endpoint: str) -> dict:
        return requests.delete(f"{self.base_url}{endpoint}", auth=self.auth).json()

# Usage
if __name__ == "__main__":
    client = WPForgeClient("https://your-site.com", "admin", "your-app-password")
    print(client.get("/status"))
