A production backup failed.

Time: {{ $timestamp }}
Host: {{ $hostname }}

Error:
{{ $errorMessage }}

Check the backup log on the VPS host:
/home/deploy/logs/nerdik-backup.log
