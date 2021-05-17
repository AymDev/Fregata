build:
	docker-compose build app

start:
	docker-compose up -d

stop:
	docker-compose down

shell:
	docker attach fregata_app