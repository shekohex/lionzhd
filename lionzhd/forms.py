from django import forms


class SearchForm(forms.Form):
    query = forms.CharField(label="Query", max_length=100)


class SeriesForm(forms.Form):
    query = forms.CharField(label="Series Query", max_length=100)


class VODForm(forms.Form):
    query = forms.CharField(label="VOD Query", max_length=100)


# [
#   {'stream_id': 123, 'kind': 'vod', 'name': 'Sherlock Holmes (2021)', 'container_extension': 'mkv' },
#   { 'stream_id': 321, 'kind': 'series', 'season': 1, 'name': 'Sherlock Holmes (2021)', 'eposide_title': 'S01E01', 'container_extension': 'mkv' }
# ]
