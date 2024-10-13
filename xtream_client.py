from meilisearch.models.document import Document as MeilisearchDocument
import requests
import json
import logging
import meilisearch
from typing import List, Dict, Optional

MOVIES_INDEX = "movies"
SERIES_INDEX = "series"


class XtreamClient:
    """
    XtreamClient class is an interface to interact with Xtream Codes API.
    """

    def __init__(
        self,
        host: str,
        port: int,
        username: str,
        password: str,
        logger: Optional[logging.Logger] = None,
    ) -> None:
        self.host = host
        self.port = port
        self.username = username
        self.password = password
        self.base_url = f"{host}:{port}"
        self.session = requests.Session()
        self._authenticated = False
        self.logger = logger or logging.getLogger(__name__)
        self.meili_client = self._setup_meilisearch_client()

    def _setup_meilisearch_client(self) -> meilisearch.Client:
        """Set up the MeiliSearch Client Connection."""
        client = meilisearch.Client("http://127.0.0.1:7700")
        if not client.is_healthy():
            self.logger.error("MeiliSearch is not healthy")
            raise SystemExit("MeiliSearch is not healthy")
        self._setup_meilisearch_indecies(client)
        return client

    def _setup_meilisearch_indecies(self, client: meilisearch.Client):
        """Set up the MeiliSearch Indexes."""
        task_info = client.create_index(MOVIES_INDEX, {"primaryKey": "stream_id"})
        task = client.wait_for_task(task_info.task_uid)
        self.logger.debug(f"MeiliSearch index {MOVIES_INDEX} task: {task}")

        task_info = client.create_index(SERIES_INDEX, {"primaryKey": "series_id"})
        task = client.wait_for_task(task_info.task_uid)
        self.logger.debug(f"MeiliSearch index {SERIES_INDEX} task: {task}")

    def _cleanup_meilisearch_indecies(self):
        """Clean up the MeiliSearch Indexes."""
        self.meili_client.delete_index(MOVIES_INDEX)
        self.meili_client.delete_index(SERIES_INDEX)

    def authenticate(self) -> bool:
        """Authenticate with the Xtream Codes API."""
        url = f"{self.base_url}/player_api.php"
        params = {"username": self.username, "password": self.password}
        try:
            response = self.session.get(url, params=params)
            response.raise_for_status()
            self.logger.debug(
                f"Authentication response: {json.dumps(response.json(), indent=2)}"
            )
            self.logger.info("Authentication successful")
            self._authenticated = True
            return True
        except requests.RequestException as e:
            self.logger.error(f"Authentication failed: {str(e)}")
            return False

    def is_authenticated(self) -> bool:
        """Check if the client is authenticated."""
        return self._authenticated

    def update(self, timeout: int = 90000) -> None:
        """Update the local database cache."""
        if not self.is_authenticated():
            raise Exception("Not authenticated. Call authenticate() first.")
        self._cleanup_meilisearch_indecies()
        self._setup_meilisearch_indecies(self.meili_client)

        self.logger.info("Updating Meilisearch database...")
        self._update_series(timeout)
        self._update_vods(timeout)
        self.logger.info("Database cache update completed")

    def _update_series(self, timeout: int):
        """Update series in the database."""
        url = f"{self.base_url}/player_api.php"
        params = {
            "username": self.username,
            "password": self.password,
            "action": "get_series",
        }
        self.logger.info("Updating series...")
        try:
            response = self.session.get(url, params=params, timeout=timeout)
            response.raise_for_status()
            series_data = response.json()
            index = self.meili_client.index(SERIES_INDEX)
            task_info = index.add_documents(series_data, primary_key="series_id")
            task = self.meili_client.wait_for_task(task_info.task_uid)
            self.logger.debug(f"Updating MeiliSearch index {SERIES_INDEX} task: {task}")

        except requests.RequestException as e:
            self.logger.error(f"Failed to update series: {str(e)}")

    def _update_vods(self, timeout: int):
        """Update VODs in the database."""
        url = f"{self.base_url}/player_api.php"
        params = {
            "username": self.username,
            "password": self.password,
            "action": "get_vod_streams",
        }
        self.logger.info("Updating VODs...")
        try:
            response = self.session.get(url, params=params, timeout=timeout)
            response.raise_for_status()
            vods_data = response.json()
            index = self.meili_client.index(MOVIES_INDEX)
            task_info = index.add_documents(vods_data, primary_key="stream_id")
            task = self.meili_client.wait_for_task(task_info.task_uid)
            self.logger.debug(f"Updating MeiliSearch index {MOVIES_INDEX} task: {task}")

        except requests.RequestException as e:
            self.logger.error(f"Failed to update VODs: {str(e)}")

    def series(self, page: int = 1, limit: int = 10) -> List[MeilisearchDocument]:
        """Get a list of series."""
        index = self.meili_client.index(SERIES_INDEX)
        response = index.get_documents({"limit": limit, "offset": (page - 1) * limit})
        return response.results

    def vods(self, page: int = 1, limit: int = 10) -> List[MeilisearchDocument]:
        """Get a list of VODs."""
        index = self.meili_client.index(MOVIES_INDEX)
        response = index.get_documents({"limit": limit, "offset": (page - 1) * limit})
        return response.results

    def search_series(self, query: str, page: int = 1, limit: int = 10) -> List[Dict]:
        """Search for series."""
        index = self.meili_client.index(SERIES_INDEX)
        response = index.search(query, {"limit": limit, "offset": (page - 1) * limit})
        return response["hits"]

    def search_vods(self, query: str, page: int = 1, limit: int = 10) -> List[Dict]:
        """Search for VODs."""
        index = self.meili_client.index(MOVIES_INDEX)
        response = index.search(query, {"limit": limit, "offset": (page - 1) * limit})
        return response["hits"]

    def series_info(self, series_id: int, timeout=60000) -> Optional[Dict]:
        """Get series info by id."""
        url = f"{self.base_url}/player_api.php"
        params = {
            "username": self.username,
            "password": self.password,
            "action": "get_series_info",
            "series_id": series_id,
        }
        self.logger.info(f"Getting Series #{series_id} info...")
        try:
            response = self.session.get(url, params=params, timeout=timeout)
            response.raise_for_status()
            infos = response.json()
            return infos
        except requests.RequestException as e:
            self.logger.error(f"Failed to get series info: {str(e)}")
            return None

    def vod_info(self, vod_id: int, timeout=60000) -> Optional[Dict]:
        """Get VOD info by id."""
        url = f"{self.base_url}/player_api.php"
        params = {
            "username": self.username,
            "password": self.password,
            "action": "get_vod_info",
            "vod_id": vod_id,
        }
        self.logger.info(f"Getting VOD #{vod_id} info...")
        try:
            response = self.session.get(url, params=params, timeout=timeout)
            response.raise_for_status()
            infos = response.json()
            return infos
        except requests.RequestException as e:
            self.logger.error(f"Failed to get VOD info: {str(e)}")
            return None

    def vod_download_uri(
        self, vod_id: int, container_extension: str, timeout=60000
    ) -> str:
        """Get VOD download URI by id."""
        return f"{self.base_url}/movie/{self.username}/{self.password}/{vod_id}.{container_extension}"

    def series_download_uri(
        self, series_id: int, container_extension: str, timeout=60000
    ) -> str:
        """Get series download URI by id."""
        return f"{self.base_url}/series/{self.username}/{self.password}/{series_id}.{container_extension}"
