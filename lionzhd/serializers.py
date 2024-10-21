from rest_framework import serializers


class DownloadItemSerializer(serializers.Serializer):
    stream_id = serializers.IntegerField()
    kind = serializers.ChoiceField(choices=["vod", "series"])
    name = serializers.CharField(max_length=255)
    container_extension = serializers.CharField(max_length=10)
    season = serializers.IntegerField(required=False)
    episode_title = serializers.CharField(max_length=255, required=False)

    def validate(self, attrs):
        if attrs["kind"] == "series":
            if "season" not in attrs:
                raise serializers.ValidationError("Season is required for series.")
            if "episode_title" not in attrs:
                raise serializers.ValidationError(
                    "Episode title is required for series."
                )
        return attrs
