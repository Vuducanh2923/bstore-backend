FROM mysql:8.0

# Data is created by Laravel migrations. Legacy dumps are intentionally not
# imported and migrations are never pre-marked as completed.
COPY init/01-create-databases.sh /docker-entrypoint-initdb.d/01-create-databases.sh
RUN sed -i 's/\r$//' /docker-entrypoint-initdb.d/01-create-databases.sh \
    && chmod 0755 /docker-entrypoint-initdb.d/01-create-databases.sh
