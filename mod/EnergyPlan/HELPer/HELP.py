import pandas as pd
from pandas import DataFrame
from pulp import LpProblem, LpMinimize, LpVariable, lpSum

'''
HELP is a linear-programming optimiser for home energy.  To use HELP, assemble
a pandas data frame with columns:
        Timestamp: The start time of a slot (typically 30 minutes); in format YYYY-MM-DD HH:ii:ss
        ImTariff: The import tariff for the slot - i.e price per KWh of electricity imported from the grid
        ExTariff: The export tariff for the slot - i.e price per KWh of electricity exported to the grid
        PV_KWh: Predicted solar (or other) generation during the slot, in KWh
        Demand_KWh: Predicted household demand in the slot, in KWh

An options argument is also required, which must contain the following keys:
    bsoc: Starting state-of-charge for the battery, in KWh
    bcap: Battery capacity, in KWh
    bcr: Battery charge rate, in KWh per time slot 
    bdr: Battery discharge rate, in KWh per time slot
    bce: Battery charge efficiency, e.g. 0.95 for a 95% charging efficiency
    bde: Battery discharge efficiency
    bmin: Minimum battery charge, in KWh

A pandas data frame is returned, with suggested battery charging/discharging values and other useful 
information.
'''
def HELP(df: DataFrame, options):

    required_columns = ["Timestamp", "ImTariff", "ExTariff", "PV_KWh", "Demand_KWh"]
    required_options = ["bsoc", "bcap", "bcr", "bdr", "bce", "bde", "bmin"]

    missing_columns = [column for column in required_columns if column not in df.columns]
    if missing_columns:
        raise ValueError("Missing required columns:", ", ".join(missing_columns))

    if df[required_columns].isnull().any().any():
        raise ValueError("Incomplete rows found in dataframe")


    missing_options = [option for option in required_options if option not in options]
    if missing_options:
        raise ValueError(" Missing required options:", ", ".join(missing_options))

    # Parameters
    T = len(df)  # Number of time slots
    initial_soc = options['bsoc']  # Initial state of charge (kWh)
    capacity = options['bcap']  # Battery capacity (kWh)
    max_charge_rate = options['bcr']  # Maximum charging rate (kWh per time slot)
    max_discharge_rate = options['bdr']  # Maximum discharging rate (kWh per time slot)
    eff_c = options['bce']  # Charging efficiency
    eff_d = options['bde']  # Discharging efficiency
    min_soc = options['bmin']  # Minimum state of charge for the battery, in KWh

    # Create the LP problem
    prob = LpProblem("Battery_Optimization", LpMinimize)

    # Decision variables
    chg = [LpVariable(f"chg_{t}", lowBound=0, upBound=max_charge_rate) for t in range(T)]  # Charging rate (kWh)
    dchg = [LpVariable(f"dchg_{t}", lowBound=0, upBound=max_discharge_rate) for t in range(T)]  # Discharging rate (kWh)
    soc = [LpVariable(f"soc_{t}", lowBound=0, upBound=capacity) for t in range(T)]  # State of charge (kWh)
    cstate = [LpVariable(f"cstate_{t}", cat="Binary") for t in range(T)]  # Battery either charges or discharges

    gmode = [LpVariable(f"gmode_{t}", cat="Binary") for t in range(T)] # The grid mode is either import or export; 1 = import, 0 = export
    gimport = [LpVariable(f"gimport_{t}", lowBound=0) for t in range(T)] # Energy imported from (or exported to) grid
    gexport = [LpVariable(f"gexport_{t}", lowBound=0) for t in range(T)]

    netdemand = [LpVariable(f"netdemand_{t}") for t in range(T)]

    # Set the objective function
    #prob += lpSum((df.iloc[t]["ImTariff"] * gimport[t]) for t in range(T))  # Minimize the cost of charging plus grid demand
    prob += lpSum((df.iloc[t]["ImTariff"] * gimport[t] - df.iloc[t]["ExTariff"] * gexport[t]) for t in range(T)) 

    # Add constraints in each time slot
    for t in range(T):

        # ----- FIXED CONSTRAINTS -----
        
        # battery charge must not exceed its min/max capacity
        prob += soc[t] <= capacity
        prob += soc[t] >= min_soc

        # battery can either charge or discharge, but not both
        prob += chg[t] <= max_charge_rate * cstate[t]
        prob += dchg[t] <= max_discharge_rate * (1 - cstate[t])

        # Ensure grid either imports or exports, not both
        # i.e either import or export must be zero
        prob += gimport[t] <= 100 * gmode[t]
        prob += gexport[t] <= 100 * (1 - gmode[t])


        # ---- VARIABLE CONSTRAINTS ----

        # Calculate net demand for the slot; that's household load minus PV generation
        prob += netdemand[t] == df.iloc[t]["Demand_KWh"] - df.iloc[t]["PV_KWh"]

        # Tie energy sources/sinks together
        prob += netdemand[t] - gimport[t] - dchg[t] + chg[t] + gexport[t] == 0

        # Avoid brown export by limiting the export amount to solar generation in the last couple of hours
        # Brown export is not allowed by Octopus SEG terms; and is probably bad for the battery
        if(t == 0):
            prob += gexport[t] <= df.iloc[t]["PV_KWh"]
        elif(t == 1):
            prob += gexport[t] <= df.iloc[t]["PV_KWh"] + df.iloc[t - 1]["PV_KWh"]
        elif(t == 2):
            prob += gexport[t] <= df.iloc[t]["PV_KWh"] + df.iloc[t - 1]["PV_KWh"] + df.iloc[t - 2]["PV_KWh"]
        else:
            prob += gexport[t] <= df.iloc[t]["PV_KWh"] + df.iloc[t - 1]["PV_KWh"] + df.iloc[t - 2]["PV_KWh"] + df.iloc[t - 3]["PV_KWh"]

        # Battery soc changes based on charging/discharging
        if t == 0: # When t-0 need to use the initial soc, otherwise the soc from previous slot
            prob += soc[t] == initial_soc + (eff_c * chg[t]) - (dchg[t] * (1 / eff_d))
        else:
            prob += soc[t] ==    soc[t-1] + (eff_c * chg[t]) - (dchg[t] * (1 / eff_d))


    # Solve the problem
    prob.solve()

    # Extract results
    results = []
    for t in range(T):
        results.append({
            "Timestamp": df.iloc[t]["Timestamp"],
            "ImportPrice": df.iloc[t]["ImTariff"],
            "ExportPrice": df.iloc[t]["ExTariff"],
            "Demand": df.iloc[t]["Demand_KWh"],
            "PV": df.iloc[t]["PV_KWh"],
            "Net": netdemand[t].varValue,
            "Charge": round(chg[t].varValue, 2),
            "Discharge": round(dchg[t].varValue, 2),
            "Import": round(gimport[t].varValue, 2),
            "Export": round(gexport[t].varValue, 2),
            "BattSOC": round(soc[t].varValue, 2),
            "GridMode": "import" if gmode[t].varValue == 1 else "export",
            "Cost": round(gimport[t].varValue * df.iloc[t]["ImTariff"] - gexport[t].varValue * df.iloc[t]["ExTariff"], 3)
        })

    results = pd.DataFrame(results)

    return results