from django.urls import path
from . import views

urlpatterns = [
    path("", views.index, name="index"),
    path("update_indexes/", views.update_indexes, name="update_indexes"),
    path("search_vods/", views.search_vods, name="search_vods"),
    path("search_series/", views.search_series, name="search_series"),
    path("series_info/<int:series_id>/", views.series_info, name="series_info"),
    path("vod_info/<int:vod_id>/", views.vod_info, name="vod_info"),
    path("configurations/", views.configurations, name="configurations"),
    path("download_streams/", views.download_streams, name="download_streams"),
    path("search_results/", views.search_results, name="search_results"),
    path("favorites/", views.favorites_list, name="favorites_list"),
    path("favorites/toggle/", views.toggle_favorite, name="toggle_favorite"),
    path(
        "favorites/check/<str:kind>/<int:stream_id>/",
        views.check_favorite,
        name="check_favorite",
    ),
]
