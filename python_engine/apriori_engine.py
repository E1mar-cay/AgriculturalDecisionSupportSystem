import sys
import mysql.connector
import pandas as pd
import time
import json
from mlxtend.preprocessing import TransactionEncoder
from mlxtend.frequent_patterns import apriori, association_rules, fpgrowth

def run_apriori_analysis():
    # Database connection parameters
    db_config = {
        'host': 'localhost',
        'database': 'db_agri_dss',
        'user': 'root',
        'password': ''
    }
    
    # Defaults
    min_support = 0.10
    min_confidence = 0.50

    # Parse command line arguments if available
    read_db_settings = True
    if len(sys.argv) >= 3:
        try:
            min_support = float(sys.argv[1])
            min_confidence = float(sys.argv[2])
            print(f"Configuration loaded from command-line: min_support={min_support:.4f}, min_confidence={min_confidence:.4f}", file=sys.stderr)
            read_db_settings = False
        except ValueError:
            print("Warning: Invalid command-line arguments. Falling back to system settings.", file=sys.stderr)

    print("Connecting to local database db_agri_dss...", file=sys.stderr)
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor(dictionary=True)
    except mysql.connector.Error as err:
        print(f"Database Connection Error: {err}", file=sys.stderr)
        sys.exit(1)

    # Fetch min_support and min_confidence dynamically from settings if not supplied by command-line
    if read_db_settings:
        try:
            cursor.execute("SELECT setting_name, setting_value FROM tbl_system_settings")
            settings = cursor.fetchall()
            for setting in settings:
                if setting['setting_name'] == 'min_support':
                    min_support = float(setting['setting_value'])
                elif setting['setting_name'] == 'min_confidence':
                    min_confidence = float(setting['setting_value'])
            print(f"Configuration loaded from database: min_support={min_support:.4f}, min_confidence={min_confidence:.4f}", file=sys.stderr)
        except mysql.connector.Error as err:
            print(f"Warning: Could not read system settings. Using defaults. Details: {err}", file=sys.stderr)

    # Fetch clean dataset
    try:
        cursor.execute("SELECT barangay, crop_type, season, intervention_received, fertilizer_type, application_type FROM tbl_rsbsa_data")
        rows = cursor.fetchall()
    except mysql.connector.Error as err:
        print(f"Database Query Error: {err}", file=sys.stderr)
        cursor.close()
        conn.close()
        sys.exit(1)

    if not rows:
        print("Dataset is empty. Run data management CSV upload first.", file=sys.stderr)
        cursor.close()
        conn.close()
        
        # Return JSON structure for empty dataset
        json_output = {
            "rules": [],
            "apriori_time_ms": 0.0,
            "fpgrowth_time_ms": 0.0
        }
        print(json.dumps(json_output))
        sys.exit(0)

    print(f"Retrieved {len(rows)} records. Constructing transaction list...", file=sys.stderr)

    # Transform tabular rows into distinct transactional items to avoid overlapping values
    transactions = []
    for row in rows:
        transaction = [
            f"barangay:{row['barangay']}",
            f"crop_type:{row['crop_type']}",
            f"season:{row['season']}",
            f"intervention_received:{row['intervention_received']}"
        ]
        
        # Safely extract and check fertilizer_type and application_type
        f_type = row.get('fertilizer_type')
        if f_type is not None and str(f_type).strip() != '':
            transaction.append(f"fertilizer_type:{str(f_type).strip()}")
            
        a_type = row.get('application_type')
        if a_type is not None and str(a_type).strip() != '':
            transaction.append(f"application_type:{str(a_type).strip()}")
            
        transactions.append(transaction)

    # Perform one-hot encoding on transactions
    te = TransactionEncoder()
    te_ary = te.fit(transactions).transform(transactions)
    df_ohe = pd.DataFrame(te_ary, columns=te.columns_)

    # 1. Mine frequent itemsets using Apriori (Benchmark)
    print("Mining frequent itemsets using Apriori algorithm...", file=sys.stderr)
    start_apriori = time.perf_counter()
    try:
        frequent_itemsets = apriori(df_ohe, min_support=min_support, use_colnames=True)
    except Exception as e:
        print(f"Apriori Algorithm Execution Error: {e}", file=sys.stderr)
        cursor.close()
        conn.close()
        sys.exit(1)
    end_apriori = time.perf_counter()
    apriori_time_ms = (end_apriori - start_apriori) * 1000.0

    # 2. Mine frequent itemsets using FP-Growth (Benchmark in parallel)
    print("Mining frequent itemsets using FP-Growth algorithm...", file=sys.stderr)
    start_fpgrowth = time.perf_counter()
    try:
        frequent_itemsets_fp = fpgrowth(df_ohe, min_support=min_support, use_colnames=True)
    except Exception as e:
        print(f"FP-Growth Algorithm Execution Error: {e}", file=sys.stderr)
        cursor.close()
        conn.close()
        sys.exit(1)
    end_fpgrowth = time.perf_counter()
    fpgrowth_time_ms = (end_fpgrowth - start_fpgrowth) * 1000.0

    # If no itemsets meet support threshold
    if frequent_itemsets.empty:
        print("No itemsets found meeting the minimum support threshold.", file=sys.stderr)
        clear_and_commit_rules(conn, cursor)
        json_output = {
            "rules": [],
            "apriori_time_ms": apriori_time_ms,
            "fpgrowth_time_ms": fpgrowth_time_ms
        }
        print(json.dumps(json_output))
        return

    # Generate association rules
    print("Extracting association rules...", file=sys.stderr)
    try:
        rules = association_rules(frequent_itemsets, metric="confidence", min_threshold=min_confidence)
    except Exception as e:
        print(f"Association Rules Generation Error: {e}", file=sys.stderr)
        cursor.close()
        conn.close()
        sys.exit(1)

    if rules.empty:
        print("No association rules met the minimum confidence threshold.", file=sys.stderr)
        clear_and_commit_rules(conn, cursor)
        json_output = {
            "rules": [],
            "apriori_time_ms": apriori_time_ms,
            "fpgrowth_time_ms": fpgrowth_time_ms
        }
        print(json.dumps(json_output))
        return

    # Clear old rules and insert new ones
    print(f"Generated {len(rules)} association rules. Writing back to database...", file=sys.stderr)
    try:
        # Clear existing rules
        cursor.execute("TRUNCATE TABLE tbl_forecast_rules")
        
        # Prepare batch insert query
        insert_query = """
            INSERT INTO tbl_forecast_rules (antecedents, consequents, support, confidence, lift) 
            VALUES (%s, %s, %s, %s, %s)
        """
        
        insert_data = []
        rules_list = []
        for _, r in rules.iterrows():
            antecedents_str = ", ".join(sorted(list(r['antecedents'])))
            consequents_str = ", ".join(sorted(list(r['consequents'])))
            support_val = float(r['support'])
            confidence_val = float(r['confidence'])
            lift_val = float(r['lift'])
            
            insert_data.append((antecedents_str, consequents_str, support_val, confidence_val, lift_val))
            
            rules_list.append({
                "antecedents": sorted(list(r['antecedents'])),
                "consequents": sorted(list(r['consequents'])),
                "support": support_val,
                "confidence": confidence_val,
                "lift": lift_val
            })
            
        cursor.executemany(insert_query, insert_data)
        conn.commit()
        print(f"Success! Inserted {len(insert_data)} association rules into tbl_forecast_rules.", file=sys.stderr)
        
        # Final JSON Output
        json_output = {
            "rules": rules_list,
            "apriori_time_ms": apriori_time_ms,
            "fpgrowth_time_ms": fpgrowth_time_ms
        }
        print(json.dumps(json_output))
        
    except mysql.connector.Error as err:
        conn.rollback()
        print(f"Database Save Error: {err}", file=sys.stderr)
        # Still return output even on database error
        json_output = {
            "rules": [],
            "apriori_time_ms": apriori_time_ms,
            "fpgrowth_time_ms": fpgrowth_time_ms
        }
        print(json.dumps(json_output))
    finally:
        cursor.close()
        conn.close()

def clear_and_commit_rules(conn, cursor):
    """Safely clears table when no rules are discovered."""
    try:
        cursor.execute("TRUNCATE TABLE tbl_forecast_rules")
        conn.commit()
        print("Truncated tbl_forecast_rules because no rules met the support/confidence thresholds.", file=sys.stderr)
    except mysql.connector.Error as err:
        print(f"Database Truncate Error: {err}", file=sys.stderr)
    finally:
        cursor.close()
        conn.close()

if __name__ == '__main__':
    run_apriori_analysis()
