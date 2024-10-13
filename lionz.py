import os
import logging
from typing import Optional

try:
    from xtream_client import XtreamClient
    import inquirer
    import aria2p
except ImportError as e:
    logging.error(f"Failed to import required modules: {e}")
    raise SystemExit("Please install required modules: xtream_client, inquirer, aria2p")


def setup_logger():
    logger = logging.getLogger(__name__)
    logger.setLevel(logging.INFO)
    formatter = logging.Formatter("%(asctime)s - %(levelname)s - %(message)s")
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(formatter)
    logger.addHandler(console_handler)
    return logger


logger = setup_logger()


class XtreamConfig:
    def __init__(self):
        self.host = os.getenv("XTREAM_CODES_API_HOST", "http://xtream-codes.com")
        self.port = int(os.getenv("XTREAM_CODES_API_PORT", 8080))
        self.username = os.getenv("XTREAM_CODES_API_USER", "username")
        self.password = os.getenv("XTREAM_CODES_API_PASS", "password")


def get_user_input(prompt: str) -> Optional[str]:
    try:
        return inquirer.text(message=prompt)
    except inquirer.errors.Aborted:
        logging.info("User aborted input")
        return None


def search_vods(x: XtreamClient, aria2: aria2p.API):
    query = get_user_input("Enter the VOD you want to search for")
    if not query:
        return

    try:
        vods = x.search_vods(query)
        if not vods:
            logging.info("No VODs found matching the query")
            return

        vod_map = {vod["name"]: vod for vod in vods}
        selected_vod = inquirer.list_input("Select a VOD", choices=list(vod_map.keys()))
        vod = vod_map[selected_vod]
        vod_id = vod["stream_id"]
        vod_container_extension = vod["container_extension"]
        logging.info(f"Getting VOD info for {selected_vod} (#{vod_id})...")
        confirm_download = inquirer.confirm("Do you want to download this VOD?")
        if confirm_download:
            opts = aria2.get_global_options()
            opts.out = f"movies/{selected_vod}/{selected_vod}.{vod_container_extension}"
            uri = x.vod_download_uri(vod_id, vod_container_extension)
            [download] = aria2.add(uri=uri, options=opts)
            logging.info(
                f"Downloading {download.name} (gid: {download.gid}) to {download.dir}/{download.options.out}"
            )
        else:
            return
        # Add code here to fetch and display VOD info
    except Exception as e:
        logging.error(f"Error occurred while searching VODs: {e}")


def search_series(x: XtreamClient, aria2: aria2p.API):
    query = get_user_input("Enter the Series you want to search for")
    if not query:
        return

    try:
        series_list = x.search_series(query)
        if not series_list:
            logging.info("No Series found matching the query")
            return

        series_map = {s["name"]: s for s in series_list}
        selected_series = inquirer.list_input(
            "Select a Series", choices=list(series_map.keys())
        )
        selected_series = series_map[selected_series]
        series_id = selected_series["series_id"]
        logging.info(
            f"Getting Series info for {selected_series.get('name')} (#{series_id})..."
        )
        series_info = x.series_info(series_id)
        if not series_info:
            logging.info("No Series info found")
            return
        seasons_list = series_info.get("seasons", ["1"])
        seasons = []
        for i in range(1, len(seasons_list) + 1):
            seasons.append(str(i))
        selected_season = inquirer.list_input("Select a Season", choices=seasons)
        episodes = series_info.get("episodes", {})
        selected_season_episodes = episodes.get(selected_season, [])
        episodes_map = {e["title"]: e for e in selected_season_episodes}
        selected_episodes = inquirer.checkbox(
            "Select Episodes", choices=[e["title"] for e in selected_season_episodes]
        )
        selected_episodes = [episodes_map[ep] for ep in selected_episodes]
        if not selected_episodes:
            logging.info("No Episodes selected")
            return
        confirm = inquirer.confirm("Do you want to download these Episodes?")
        if confirm:
            for e in selected_episodes:
                opts = aria2.get_global_options()
                season = f"Season 0{selected_season}"
                opts.out = f"shows/{selected_series.get('name')}/{season}/{e['title']}.{e['container_extension']}"
                uri = x.series_download_uri(e["id"], e["container_extension"])
                [download] = aria2.add(uri=uri, options=opts)
                logging.info(
                    f"Downloading {download.name} (gid: {download.gid}) to {download.dir}/{download.options.out}"
                )
    except Exception as e:
        logging.error(f"Error occurred while searching Series: {e}")


def update_indexes(x: XtreamClient, _aria2: aria2p.API):
    confirm = inquirer.confirm("Are you sure you want to update MeiliSearch indexes?")
    if not confirm:
        logging.info("Update cancelled")
        return

    try:
        logging.info("Updating MeiliSearch indexes...")
        x.update()
        logging.info("MeiliSearch indexes updated successfully")
    except Exception as e:
        logging.error(f"Error occurred while updating MeiliSearch indexes: {e}")


def main():
    config = XtreamConfig()

    try:
        x = XtreamClient(
            host=config.host,
            port=config.port,
            username=config.username,
            password=config.password,
            logger=logger.getChild("XtreamClient"),
        )
        x.authenticate()
        if not x.is_authenticated():
            raise ValueError("Authentication failed")
    except Exception as e:
        logging.error(f"Failed to initialize XtreamClient: {e}")
        return

    aria2 = aria2p.API(aria2p.Client(host="http://umbrel", port=6800, secret="umbrel"))
    actions = {
        "Search VODs": search_vods,
        "Search Series": search_series,
        "Update MeiliSearch Indexes": update_indexes,
    }

    while True:
        action = inquirer.list_input(
            "What do you want to do?", choices=list(actions.keys()) + ["Exit"]
        )

        if action == "Exit":
            break

        actions[action](x, aria2)


if __name__ == "__main__":
    main()
