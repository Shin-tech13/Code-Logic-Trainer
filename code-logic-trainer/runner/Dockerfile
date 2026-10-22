FROM node:22-alpine

RUN apk add --no-cache docker-cli

WORKDIR /runner
COPY server.mjs ./

ENV PORT=8080
EXPOSE 8080

CMD ["node", "server.mjs"]
