import os


class XtreamConfig:
    host: str
    port: int
    username: str
    password: str
    meili_url: str
    meili_api_key: str
    aria2_rpc_host: str
    aria2_rpc_port: int
    user_agent: str

    def __init__(self):
        self.host = os.getenv("XTREAM_CODES_API_HOST", "http://xtream-codes.com")
        self.port = int(os.getenv("XTREAM_CODES_API_PORT", 8080))
        self.username = os.getenv("XTREAM_CODES_API_USER", "username")
        self.password = os.getenv("XTREAM_CODES_API_PASS", "password")
        self.meili_url = os.getenv("MEILI_HTTP_URL", "http://umbrel:7700")
        self.meili_api_key = os.getenv("MEILI_MASTER_KEY", "")
        self.aria2_rpc_host = os.getenv("ARIA2_RPC_HOST", "umbrel")
        self.aria2_rpc_port = int(os.getenv("ARIA2_RPC_PORT", 6800))
        self.aria2_rpc_secret = os.getenv("ARIA2_RPC_SECRET", "")
        self.user_agent = os.getenv(
            "USER_AGENT",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:133.0) Gecko/20100101 Firefox/133.0",
        )
