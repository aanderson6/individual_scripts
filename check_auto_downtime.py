#!/usr/bin/env python3

import requests
import json
import time
from datetime import datetime, timedelta
import sys
from icinga2api.client import Client

#takes three parameters as input - one is the hostname, the second is the name of the auto-downtime exclusion group, the third is how many hours devices should automatically be in scheduled downtime
# :: auto_downtime_check.py icinga_hostname.domain.com testingtest2 .1

def main(host_name, auto_sch_downtime_exclusion_group, auto_sch_downtime_hours):
    #changing the template (to change the group) for changing application of scheduled downtime

    auto_sch_downtime_hours = int(auto_sch_downtime_hours)
    current_host = icinga_api_host(host_name)
    current_hour, current_min = map (str, time.strftime("%H %M").split())

    #print(current_host)

    #if init_time isn't set, set it - this must be the first run
    if (not 'vars' in current_host):
        set_initial_time(host_name)

        if (auto_sch_downtime_hours != 0):
            #schedule('Host', r'(!("auto_sch_downtime_exclusion_group" in host.groups) && (host.name == "apitesthost"))', 'root', 'Test downtime for Auto-Downtime', current_hour + ':' + current_min, str(auto_sch_downtime_hours) + ':00:00')
            schedule('Host', r'(host.name == "' + host_name + '")', 'root', 'Auto-Downtime', current_hour + ':' + current_min, str(auto_sch_downtime_hours) + ':00:00')
    else:
        if (not 'init_time' in current_host['vars']):
            set_initial_time(host_name)

            if (auto_sch_downtime_hours != 0):
                #schedule('Host', r'(!("auto_sch_downtime_exclusion_group" in host.groups) && (host.name == "apitesthost"))', 'root', 'Test downtime for Auto-Downtime', current_hour + ':' + current_min, str(auto_sch_downtime_hours) + ':00:00')
                schedule('Host', r'(host.name == "' + host_name + '")', 'root', 'Auto-Downtime', current_hour + ':' + current_min, str(auto_sch_downtime_hours) + ':00:00')
        elif (datetime.now() - datetime.fromtimestamp(current_host['vars']['init_time']) >= timedelta(hours=auto_sch_downtime_hours)):
            #set new template
            #icinga_api_host(host_name, 'POST', {"imports": new_template_name})
            if ('groups' in current_host):
                current_groups = current_host['groups']
                current_groups.append(auto_sch_downtime_exclusion_group)
                icinga_api_host(host_name, 'POST', {"groups": current_groups})
                print(current_groups)
            else:
                icinga_api_host(host_name, 'POST', {"groups": [auto_sch_downtime_exclusion_group]})

    #print(auto_sch_downtime_hours)
    sys.exit(0)

def set_initial_time(hostname):
    current_time = int(time.time())
    new_init_time = {"vars": { "init_time": current_time }}
    icinga_api_host(hostname, 'POST', new_init_time)

#boilerplate code to access the icingaweb2 api
def icinga_api_host(hostname, method='GET', update_data=None):
    director_api_login = "api_user"
    director_api_password = "password"
    request_url = "https://icinga.domain.org/icingaweb2/director/host?name="
    headers = { 'Accept': 'application/json' }
    if (method == 'GET'):
        result = requests.get(request_url+hostname,
            headers=headers,
            auth=(director_api_login, director_api_password))
        return result.json() # return json as dictionary
    else:
        headers["X-HTTP-Method-Override"] = "POST"
        result = requests.post(request_url+hostname,
            headers=headers,
            data=json.dumps(update_data),
            auth=(director_api_login, director_api_password))
        return result # hopefully returns 200

c = Client('https://icinga.apiserver.com:portnumber',
    certificate='/var/lib/icinga2/certs/domain.crt',
    key='/var/lib/icinga2/certs/domain.key',
    ca_certificate='/var/lib/icinga2/certs/ca.crt')

def hms_to_secs(t):
    h, m, s = [int(i) for i in t.split(':')]
    return 3600*h + 60*m + s

def schedule(t, match, user, comment, s, d):
    # t = 'Host' or 'Service'

    now = time.localtime()
    epoch = int(time.mktime(now))
    day = time.strftime('%w', now)

    duration=hms_to_secs(d)

    start = int(time.mktime(time.strptime(s, '%H:%M')))
    if start < epoch:
        start = epoch
    end = start + duration

    print('\nScheduling "' + t + '" downtime for "' + comment + '" starting at ' + s + ' for ' + d + ':')

    try:
        r = c.actions.schedule_downtime(t, match, user, comment, start, end, duration, fixed=True)
    except Exception as e:
        print('Error ' + str(e))
        return

    for s in r['results']:
        print(str(int(s['code'])) + ': ' + str(s['status']))

main(sys.argv[1], sys.argv[2], sys.argv[3])