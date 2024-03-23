#!/bin/bash

# Part of the Progressable package
# https://github.com/verseles/progressable

# Function to display help
function show_help {
  echo "Usage: $0 [options]"
  echo "Options:"
  echo "  --folder=\"path\"                 Specifies a folder to merge files (can be used multiple times)" 
  echo "  --folder-recursive=\"path\"       Specifies a folder to merge files recursively (can be used multiple times)"
  echo "  --output=\"file name\"            Specifies the output file name (default: output.txt)"
  echo "  --ignore-extensions=\"extensions\" Specifies file extensions to ignore (comma separated)"
  echo "  --ignore-folders=\"folders\"      Specifies folders to globally ignore (can be used multiple times)"
  echo "  --ignore-files=\"files\"          Specifies files to globally ignore (can be used multiple times)"
  echo ""
  echo "Examples:"
  echo "  $0 --folder=/path/folder1 --folder=/path/folder2"
  echo "  $0 --folder-recursive=/path/folder --output=result.txt"
  echo "  $0 --folder=/path/folder1 --folder-recursive=/path/folder2 --ignore-extensions=txt,log --ignore-files=file1,file2"
}

# Variables to store parameters
folders=()
recursive_folders=()
output_file="output.txt" 
ignore_extensions=""
ignore_folders=()
ignore_files=()

# Process parameters
while [[ $# -gt 0 ]]; do
  case "$1" in
    --folder=*)
      folders+=("${1#*=}")
      shift
      ;;
    --folder-recursive=*)  
      recursive_folders+=("${1#*=}")   
      shift
      ;;
    --output=*)
      output_file="${1#*=}"
      shift
      ;;
    --ignore-extensions=*)
      ignore_extensions="${1#*=}"    
      shift
      ;;  
    --ignore-folders=*)
      ignore_folders+=("${1#*=}")
      shift
      ;;
    --ignore-files=*)
      ignore_files+=("${1#*=}")
      shift
      ;;
    *)
      echo "Invalid option: $1"
      show_help  
      exit 1
      ;;
  esac
done

# Clear output file otherwise it will append
truncate -s 0 "$output_file"

# Verify if no parameter was provided
if [[ ${#folders[@]} -eq 0 && ${#recursive_folders[@]} -eq 0 ]]; then
  show_help
  exit 0
fi

# Function to process a folder  
function process_folder {
  local folder="$1"
  for file in "$folder"/*; do
    if [[ -f "$file" ]]; then
      local filename="${file##*/}"
      if [[ "$file" != "$output_file" && "$folder/$output_file" != "$file" ]]; then
        if [[ " ${ignore_files[*]} " != *" $filename "* ]]; then
          local extension="${file##*.}"
          if [[ "$ignore_extensions" != *"$extension"* ]]; then
            ignore_match=false
            for ignored_folder in ${ignore_folders[*]}; do
              if [[ "${file}/" =~ ${ignored_folder} ]]; then
                ignore_match=true
                break
              fi
            done

            if [[ ${ignore_match} == false ]]; then
              echo "Adding file: $file"
              echo "FILE: $file" >> "$output_file"
              cat "$file" >> "$output_file"
              echo "" >> "$output_file"
            fi
          fi
        fi  
      fi
    fi
  done
}

# Function to process a folder recursively
function process_folder_recursive {
  local folder="$1"
  for file in "$folder"/*; do
    if [[ -f "$file" ]]; then
      local filename="${file##*/}"
      if [[ "$file" != "$output_file" && "$folder/$output_file" != "$file" ]]; then
        if [[ " ${ignore_files[*]} " != *" $filename "* ]]; then
          local extension="${file##*.}"
          if [[ "$ignore_extensions" != *"$extension"* ]]; then
            ignore_match=false
            for ignored_folder in ${ignore_folders[*]}; do
              if [[ "${file}/" =~ ${ignored_folder} ]]; then
                ignore_match=true
                break
              fi
            done

            if [[ ${ignore_match} == false ]]; then
              echo "Adding file recursively: $file"
              echo "FILE: $file" >> "$output_file"
              cat "$file" >> "$output_file"
              echo "" >> "$output_file"
            fi
          fi
        fi
      fi
    elif [[ -d "$file" ]]; then
      process_folder_recursive "$file" # Recursively call the function for subfolders
    fi
  done
}

# Process specified folders
for folder in "${folders[@]}"; do
  process_folder "$folder"
done

# Process specified folders recursively
for folder in "${recursive_folders[@]}"; do
  process_folder_recursive "$folder"  
done

echo "Output file: $output_file"
