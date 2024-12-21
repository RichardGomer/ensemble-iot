import sys
import pandas as pd
import json
from HELP import HELP

input_data = sys.stdin.read()
data = json.loads(input_data)

options = data.get('options', {})
data = data.get('data', [])

df = pd.DataFrame(data)

try:
    plan = HELP(df, options)
    print(plan.to_json(orient='records', lines=True))
    sys.exit(0)
except ValueError as e:
    print("Bad input. " + str(e))
    sys.exit(1)


