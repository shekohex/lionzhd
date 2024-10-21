# Use the official Python image from the Docker Hub
FROM python:3.12-slim-bookworm

# Set environment variables
ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates && apt-get clean

ADD https://astral.sh/uv/0.4.25/install.sh /uv-installer.sh
# Run the installer then remove it
RUN sh /uv-installer.sh && rm /uv-installer.sh

# Ensure the installed binary is on the `PATH`
ENV PATH="/root/.cargo/bin/:$PATH"
ENV PATH="/app/.venv/bin:$PATH"

# Copy the project files
COPY . /app/
# Set the working directory
WORKDIR /app
# Install Python dependencies
RUN uv sync --frozen

# Collect static files
RUN python manage.py collectstatic --noinput

# Run database migrations
RUN python manage.py migrate

ENV XTREAM_CODES_API_HOST=lionzhd.com
ENV XTREAM_CODES_API_PORT=8080
ENV XTREAM_CODES_API_USER=alice
ENV XTREAM_CODES_API_PASS=secret
ENV MEILI_HTTP_URL=http://umbrel:7700
ENV MEILI_MASTER_KEY=master_key
ENV DEBUG=False
ENV DJANGO_ALLOWED_HOSTS=*
ENV DJANGO_SECRET_KEY=secret_key

# Expose the port the app runs on
EXPOSE 8000

# Start the ASGI server
CMD ["uvicorn", "web.asgi:application", "--host", "0.0.0.0", "--port", "8000"]
