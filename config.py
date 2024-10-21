import os


class XtreamConfig:
    def __init__(self):
        self.host = os.getenv("XTREAM_CODES_API_HOST", "http://xtream-codes.com")
        self.port = int(os.getenv("XTREAM_CODES_API_PORT", 8080))
        self.username = os.getenv("XTREAM_CODES_API_USER", "username")
        self.password = os.getenv("XTREAM_CODES_API_PASS", "password")
        self.meili_url = os.getenv("MEILI_HTTP_URL", "http://umbrel:7700")
        self.meili_api_key = os.getenv("MEILI_MASTER_KEY", "")
