# Dünner Passthrough auf das offizielle Ollama-Image. GPU kommt per Compose-Override (CDI),
# nicht hier. Modelle werden NICHT ins Image gebacken — sie kommen zur Laufzeit über den
# ModelRouter (config/model_routes.yaml), kein OLLAMA_MODEL-env-bleed (CLAUDE.md-Invariante).
#
# WHY eigener Build statt direktem image:-Verweis: ein fester Erweiterungs-Punkt, falls später
# ein Warmup/Pre-Pull eines Modells nötig wird — ohne das Compose anfassen zu müssen.
FROM ollama/ollama:latest
