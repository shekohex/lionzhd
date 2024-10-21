from typing import Optional
from django.http.request import HttpRequest
from django.shortcuts import render, redirect
from django.http import JsonResponse
from django.core.cache import cache
from rest_framework.decorators import api_view
from rest_framework.response import Response
from rest_framework import status
from .serializers import DownloadItemSerializer
from .forms import SearchForm, SeriesForm, VODForm
from .models import XtreamConfig as XtreamConfigModel
from xtream_client import XtreamClient
from config import XtreamConfig as EnvXtreamConfig
import aria2p
import logging

logger = logging.getLogger(__name__)


class XtreamConfig:
    def __init__(self):
        config = XtreamConfigModel.objects.first()
        if config:
            self.host = config.host
            self.port = config.port
            self.username = config.username
            self.password = config.password
            self.meili_url = config.meili_url
            self.meili_api_key = config.meili_api_key
        else:
            env_config = EnvXtreamConfig()
            self.host = env_config.host
            self.port = env_config.port
            self.username = env_config.username
            self.password = env_config.password
            self.meili_url = env_config.meili_url
            self.meili_api_key = env_config.meili_api_key


def get_xtream_client():
    client = cache.get("xtream_client")
    if not client:
        config = XtreamConfig()
        client = XtreamClient(
            host=config.host,
            port=config.port,
            username=config.username,
            password=config.password,
            logger=logger.getChild("XtreamClient"),
            meili_url=config.meili_url,
            meili_api_key=config.meili_api_key,
        )
        client.authenticate()
        if not client.is_authenticated():
            raise ValueError("Authentication failed")
        cache.set("xtream_client", client, timeout=3600)
    return client


def index(request: HttpRequest):
    form = SearchForm()
    return render(request, "index.html", {"form": form})

def search_results(request: HttpRequest):
    query = request.GET.get("query", "")
    if not query:
        return render(request, "search_results.html", {"error": "No query provided"})
    client = get_xtream_client()
    try:
        vods = client.search_vods(query)
        series = client.search_series(query)
        results = {"vods": vods, "series": series}
        return render(request, "search_results.html", {"results": results, "query": query})
    except Exception as e:
        return render(request, "search_results.html", {"error": str(e), "query": query})

def update_indexes(request: HttpRequest):
    if request.method == "POST":
        client = get_xtream_client()
        try:
            client.update()
            return JsonResponse(
                {"status": "success", "message": "Indexes updated successfully"}
            )
        except Exception as e:
            return JsonResponse({"status": "error", "message": str(e)})
    return render(request, "update_indexes.html")


def search_vods(request: HttpRequest):
    if request.method == "POST":
        form = VODForm(request.POST)
        if form.is_valid():
            query = form.cleaned_data["query"]
            client = get_xtream_client()
            try:
                vods = client.search_vods(query)
                return render(request, "vod_results.html", {"vods": vods})
            except Exception as e:
                return render(
                    request, "search_vods.html", {"form": form, "error": str(e)}
                )
    else:
        form = VODForm()
    return render(request, "search_vods.html", {"form": form})


def search_series(request: HttpRequest):
    if request.method == "POST":
        form = SeriesForm(request.POST)
        if form.is_valid():
            query = form.cleaned_data["query"]
            client = get_xtream_client()
            try:
                series_list = client.search_series(query)
                return render(
                    request, "series_results.html", {"series_list": series_list}
                )
            except Exception as e:
                return render(
                    request, "search_series.html", {"form": form, "error": str(e)}
                )
    else:
        form = SeriesForm()
    return render(request, "search_series.html", {"form": form})


def series_info(request: HttpRequest, series_id: int):
    client = get_xtream_client()
    try:
        series_info = client.series_info(series_id)
        if not series_info:
            raise ValueError("No series info found")

        # Extract series info
        info = series_info.get("info", {})
        seasons = series_info.get("seasons", [])
        episodes = series_info.get("episodes", {})
        if len(seasons) == 0:
            # No Seasons? add a default season
            seasons_len = len(episodes)
            for i in range(seasons_len):
                name = f"Season {i + 1}"
                eposides_count = len(episodes.get(i, []))
                season_number = i + 1
                seasons.append({"name": name, "eposide_count": eposides_count, "season_number": season_number})
        context = {
            "info": info,
            "seasons": seasons,
            "episodes": episodes,
        }
        return render(request, "series_info.html", context)
    except Exception as e:
        return render(request, "series_info.html", {"error": str(e)})


def vod_info(request: HttpRequest, vod_id: int):
    client = get_xtream_client()
    try:
        vod_info = client.vod_info(vod_id)
        if not vod_info:
            raise ValueError("No VOD info found")

        # Extract VOD info
        info = vod_info.get("info", {})
        movie_data = vod_info.get("movie_data", {})

        context = {
            "info": info,
            "movie_data": movie_data,
        }
        return render(request, "vod_info.html", context)
    except Exception as e:
        logger.error(f"Error retrieving VOD info: {e}")
        return render(request, "vod_info.html", {"error": str(e)})


@api_view(["POST"])
def download_streams(request: HttpRequest, format="json"):
    serializer = DownloadItemSerializer(data=request.body, many=True)
    if serializer.is_valid():
        client = get_xtream_client()
        aria2 = aria2p.API(
            aria2p.Client(host="http://umbrel", port=6800, secret="umbrel")
        )
        items = serializer.data
        downloads = []

        try:
            for item in items:
                if item["kind"] == "vod":
                    uri = client.vod_download_uri(
                        item["stream_id"], item["container_extension"]
                    )
                    opts = aria2.get_global_options()
                    opts.out = f"movies/{item['name']}/{item['name']}.{item['container_extension']}"
                elif item["kind"] == "series":
                    uri = client.series_download_uri(
                        item["stream_id"], item["container_extension"]
                    )
                    opts = aria2.get_global_options()
                    opts.out = f"shows/{item['name']}/Season 0{item['season']}/{item['episode_title']}.{item['container_extension']}"
                else:
                    return Response(
                        {"status": "error", "message": "Invalid kind"},
                        status=status.HTTP_400_BAD_REQUEST,
                    )
                [download] = aria2.add(uri=uri, options=opts)
                downloads.append(download)
                logger.info(
                    f"Downloading {download.name} (gid: {download.gid}) to {download.dir}/{download.options.out}"
                )

            return Response(
                {
                    "status": "success",
                    "message": "Downloads started successfully",
                    "downloads": downloads,
                },
                status=status.HTTP_200_OK,
            )
        except Exception as e:
            logger.error(f"Error occurred while downloading streams: {e}")
            return Response(
                {"status": "error", "message": str(e)},
                status=status.HTTP_500_INTERNAL_SERVER_ERROR,
            )
    else:
        return Response(serializer.errors, status=status.HTTP_400_BAD_REQUEST)


def configurations(request):
    config = XtreamConfig()
    return JsonResponse(
        {
            "host": config.host,
            "port": config.port,
            "username": config.username,
            "password": config.password,
            "meili_url": config.meili_url,
            "meili_api_key": config.meili_api_key,
        }
    )
