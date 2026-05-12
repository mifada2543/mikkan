"""
LLM Model management and inference
"""
import os
import glob
import traceback
from llama_cpp import Llama

try:
    from .config import LLM_DIR, LLM_CONFIG, LLM_INFERENCE_NORMAL, SYSTEM_INFO
except ImportError:
    from config import LLM_DIR, LLM_CONFIG, LLM_INFERENCE_NORMAL, SYSTEM_INFO

class LLMManager:
    """Manage LLM loading and inference"""
    
    def __init__(self):
        self.model = None
        self.selected_model_path = None
        self.optimal_threads = 8
    
    def find_available_models(self):
        """Scan and return available GGUF models"""
        gguf_files = sorted(glob.glob(os.path.join(LLM_DIR, "*.gguf")))
        return gguf_files
    
    def display_model_selection(self, gguf_files):
        """Display model selection UI"""
        if not gguf_files:
            print(f"[ERROR] Tidak ada file .gguf ditemukan di {LLM_DIR}")
            return None
        
        print("\n" + "="*60)
        print("🤖 MIKKAN AI - Model Selection".upper())
        print("="*60)
        print(f"\nModel tersedia di: {LLM_DIR}\n")
        
        # Display choices
        for idx, model_path in enumerate(gguf_files, 1):
            model_name = os.path.basename(model_path)
            model_size = os.path.getsize(model_path) / (1024**3)  # Convert to GB
            print(f"  {idx}. {model_name:<35} ({model_size:.2f} GB)")
        
        print("\n" + "-"*60)
        
        # Get user input
        while True:
            try:
                choice = input(f"\nPilih model (1-{len(gguf_files)}): ").strip()
                choice_idx = int(choice) - 1
                
                if 0 <= choice_idx < len(gguf_files):
                    selected_model = gguf_files[choice_idx]
                    selected_name = os.path.basename(selected_model)
                    print(f"\n✅ Model dipilih: {selected_name}")
                    print("="*60 + "\n")
                    return selected_model
                else:
                    print(f"❌ Pilihan tidak valid. Masukkan angka 1-{len(gguf_files)}")
            except ValueError:
                print(f"❌ Input tidak valid. Masukkan angka 1-{len(gguf_files)}")
    
    def setup_cpu_config(self):
        """Setup optimal CPU configuration"""
        try:
            import psutil
            cpu_count = psutil.cpu_count(logical=False) or 10
            self.optimal_threads = max(6, cpu_count - 2)  # Leave 2 cores for OS
            
            print(f"[INFO] ✓ CPU Cores: {cpu_count} (P-cores + E-cores)")
            print(f"[INFO] ✓ Threads: {self.optimal_threads} (reserved 2 for OS)")
        except ImportError:
            print("[INFO] psutil not found, using default threads=8")
            self.optimal_threads = 8
    
    def load(self, interactive=True):
        """Load LLM model"""
        gguf_files = self.find_available_models()
        
        if interactive:
            self.selected_model_path = self.display_model_selection(gguf_files)
        else:
            # Auto-select first available model
            if gguf_files:
                self.selected_model_path = gguf_files[0]
                print(f"[INFO] Auto-selected model: {os.path.basename(self.selected_model_path)}")
        
        if not self.selected_model_path:
            print("[ERROR] No model selected. Exiting...")
            return False
        
        print(f"[INFO] Loading model from: {self.selected_model_path}")
        print(f"[INFO] Model file exists: {os.path.exists(self.selected_model_path)}")
        print(f"[INFO] System: {SYSTEM_INFO['name']} ({SYSTEM_INFO['description']})")
        
        self.setup_cpu_config()
        
        print(f"[INFO] ✓ Context Window: {LLM_CONFIG['n_ctx']} tokens (optimized for i3-1220P RAM)")
        print(f"[INFO] ✓ Batch Size: {LLM_CONFIG['n_batch']} (reduced for memory efficiency)")
        print(f"[INFO] ✓ GPU Layers: {LLM_CONFIG['n_gpu_layers']} (iGPU offloading disabled - CPU-only for better speed)")
        print(f"[INFO] ✓ Temperature: {LLM_INFERENCE_NORMAL['temperature']} (deterministic outputs)")
        print(f"[INFO] Starting initialization...\n")
        
        try:
            self.model = Llama(
                model_path=self.selected_model_path,
                n_threads=self.optimal_threads,
                **LLM_CONFIG
            )
            print("[INFO] ✅ Model loaded successfully!")
            print("[INFO] Ready for chat inference.\n")
            return True
        except Exception as e:
            print(f"[ERROR] Failed to load model: {e}")
            traceback.print_exc()
            self.model = None
            return False
    
    def infer(self, prompt, **overrides):
        """Run inference on prompt with optional per-request overrides."""
        if self.model is None:
            raise RuntimeError("Model not loaded")
        
        try:
            inference_options = dict(LLM_INFERENCE_NORMAL)
            inference_options.update(overrides)
            output = self.model(
                prompt,
                **inference_options
            )
            return output
        except RuntimeError as e:
            print(f"[ERROR] LLM inference error: {e}")
            raise
    
    def is_loaded(self):
        """Check if model is loaded"""
        return self.model is not None

# Global LLM instance
llm_manager = None

def get_llm_manager():
    """Get global LLM manager instance"""
    global llm_manager
    if llm_manager is None:
        llm_manager = LLMManager()
    return llm_manager

def initialize_llm(interactive=True):
    """Initialize LLM model"""
    manager = get_llm_manager()
    return manager.load(interactive=interactive)
