#!/usr/bin/env python3
import subprocess, json, requests, datetime, sys, time

# Change this
SERVER_URL = "https://YOURESITE.com/nbn/receive.php"

# This should be the same as the receive.php file
# Create your own: https://www.uuidgenerator.net/version4
API_KEY = "d7d140c6-8739-4aa0-b25a-207ef7ec8bbd"

RETRIES = 3

def run_speedtest():
    for attempt in range(RETRIES):
        try:
            cp = subprocess.run(
                ["speedtest", "--format=json", "--accept-license", "--accept-gdpr"],
                capture_output=True, text=True, check=True
            )
            data = json.loads(cp.stdout)
            def bps_to_mbps(b): return (b*8)/1_000_000

            payload = {
                "tested_at_utc": datetime.datetime.utcnow().replace(microsecond=0).isoformat()+'Z',
                "ping_ms": data["ping"]["latency"],
                "jitter_ms": data["ping"].get("jitter"),
                "packet_loss_pct": data.get("packetLoss"),
                "download_mbps": bps_to_mbps(data["download"]["bandwidth"]),
                "upload_mbps": bps_to_mbps(data["upload"]["bandwidth"]),
                "server_id": data["server"].get("id"),
                "server_name": data["server"].get("name"),
                "isp": data.get("isp"),
                "iface": data.get("interface", {}).get("name")
            }

            headers = {"Content-Type":"application/json","X-API-Key":API_KEY}
            r = requests.post(SERVER_URL, headers=headers, data=json.dumps(payload), timeout=20)
            r.raise_for_status()
            print("Uploaded:", payload["tested_at_utc"])
            break
        except Exception as e:
            print(f"Attempt {attempt+1} failed:", e, file=sys.stderr)
            time.sleep(5)
    else:
        print("All attempts failed", file=sys.stderr)
        sys.exit(1)

if __name__=="__main__":
    run_speedtest()
