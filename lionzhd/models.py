from django.db import models

# Create your models here.
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
