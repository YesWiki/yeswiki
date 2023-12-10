# Build image


```
docker compose build
```
# Launch image

- `docker compose up -d`
- yeswiki should be accessible at `localhost:8085`

# Dev version

- allow www-data to right local directory
This version should map the local repository to your docker container.

- `docker compose up -f docker-compose-dev.yml`

