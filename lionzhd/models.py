from django.db import models


class XtreamConfig(models.Model):
    host = models.CharField(max_length=255, default="http://xtream-codes.com")
    port = models.IntegerField(default=8080)
    username = models.CharField(max_length=255, default="username")
    password = models.CharField(max_length=255, default="password")
    meili_url = models.CharField(max_length=255, default="http://umbrel:7700")
    meili_api_key = models.CharField(max_length=255, default="")
    aeia2_rpc_host = models.CharField(max_length=255, default="umbrel")
    aria2_rpc_port = models.IntegerField(default=6800)
    aria2_rpc_secret = models.CharField(max_length=255, default="")

    def __str__(self):
        return f"XtreamConfig ({self.host}:{self.port})"


class FavoriteItem(models.Model):
    KIND_CHOICES = [
        ("series", "Series"),
        ("vod", "VOD"),
    ]
    kind = models.CharField(max_length=10, choices=KIND_CHOICES)
    stream_id = models.IntegerField()
    name = models.CharField(max_length=255)
    image = models.URLField()
    added = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ["-added"]

    def __str__(self):
        return f"{self.kind}: {self.name}"
