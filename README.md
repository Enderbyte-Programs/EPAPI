# EPAPI
PHP-SQL API and Authentication System

## Variants

### EPAPI v4 (2025)

The latest insecure/limited security version of EPAPI with more settings, better support for CLI, and transfer statistics.

### EPAPI v3S (2025)

The latest secure version of EPAPI (has the privilege system and the account system). It is single namespace. 

### EPAPI v2N (2025)

This version has namespaces, but no accounts. This is called 2N instead of 3N because it has no security.

### EPAPI v2 (2024)

This is a rather insecure version that you probably don't want to use. It also has no support for the CLI system (only SQL). It too is lost to time and will not be restored.

### EPAPI v1 (2024)

This... is not usable anymore. Don't ask about it. It doesn't exist. It has a horrible design.

## Missing Variants

There are some gaps. Here are their explanations:

- v1 is deliberately not uploaded due to its poor quality
- v3 and v3N simply do not exist, as the whole point of v3 was to add security. v3 was then retroactively renamed to v3S when v4 came out because v4 was insecure.

## The Future

I plan to write EPAPI v5 which includes:
- Software to control the operation
- Web UI to control accounts
- fine-grained permission setup in addition to the basic privilege level

v5 will theoretically only have v5S as v5S will have all namespaces and having "security" will be mandatory (even if needsauth will be set to false).
All of the software controls found here to help you set up will only work for v5, though instructions are provided below on how to set up older versions.
