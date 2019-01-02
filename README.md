# ttphp

```
server {
	location ~ /(\.|var|README) {
		deny all;
	}
	location ~ ^/api {
		rewrite ^/api(.*)?(.*)$ /index.php?r=$1$2 last;
	}
}
```